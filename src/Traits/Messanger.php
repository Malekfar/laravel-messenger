<?php
namespace Cmgmyr\Messenger\Traits;

use App\User;
use Cmgmyr\Messenger\Models\Message;
use Cmgmyr\Messenger\Models\Thread;
use ElephantIO\Client;
use ElephantIO\Engine\SocketIO\Version2X;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait Messanger
{
    private function init()
    {
        if (!$this->url)
            $this->url = "http://192.168.10.10:3002";
    }

    public function store()
    {
        $this->init();
        if (!$this->url)
            return false;
        $data = request()->input();
        if (
            ($data['user_id'] != '' && $data['thread_id'] != '') ||
            ($data['user_id'] == '' && $data['thread_id'] == '')
        )
            return response()->json(['something went wrong']);
        $this->sendMessage($data);
        return response()->json(['status' => true, 'message' => 'message is send']);
    }

    public function publishOnSocket($threadId, $userId, $message)
    {
        $version = new Version2X($this->url, [
            'headers' => [
                'X-My-Header: websocket rocks',
                'Authorization: Bearer 12b3c4d5e6f7g8h9i'
            ]
        ]);
        $client = new Client($version);
        $client->initialize();
        $client->emit("New_Message", ['data' => ['message' => $message, 'user_id' => $userId], "thread" => $threadId]);
        $client->close();
    }

    public function show()
    {
        $threads = Thread::forUser(Auth::id())->latest('updated_at')->get()->pluck('id')->toArray();
        if (!in_array(request()->input()['thread_id'], $threads))
            return "false";
        $thread = Thread::findOrfail(request()->input()['thread_id']);
        return response()->json(['logged_in_user' => auth()->user()->id, 'messages' => $thread->messages()->get()->keyBy('id')->map(function (Message $message) {
            return ['user_id' => $message->user_id, 'body' => $message->body, 'created_at' => $message->created_at];
        })->toArray()]);
    }

    public function showLast()
    {
        $threads = Thread::forUser(Auth::id())->latest('updated_at')->get()->pluck('id')->toArray();
        if (!in_array(request()->input()['thread_id'], $threads))
            return "false";
        $thread = Thread::findOrfail(request()->input()['thread_id']);
        return response()->json(['logged_in_user' => auth()->user()->id, 'messages' => $thread->messages()->orderBy('created_at', 'desc')->take(20)->get()->keyBy('id')->map(function (Message $message) {
            return ['user_id' => $message->user_id, 'body' => $message->body, 'created_at' => $message->created_at];
        })->toArray()]);
    }

    private function sendMessage($data)
    {
        if ($data['user_id'] == '')
            $thread = Thread::findOrfail($data['thread_id']);
        if ($data['thread_id'] == '') {
            $user = User::findOrfail($data['user_id']);
            if ($this->privateThread(Auth::user()->id, $data['user_id']))
                $thread = $this->privateThread(Auth::user()->id, $data['user_id']);
            else
                $thread = $this->makePrivateThread($user);
        }
        if (Message::create([
            'thread_id' => $thread->id,
            'user_id' => Auth::user()->id,
            'body' => $data['message'],
        ]))
            $this->publishOnSocket($thread->id, Auth::user()->id, $data['message']);
    }

    public function privateThread($userId, $participentId)
    {
        $sql = <<< SQL
            SELECT * FROM `threads`
            LEFT JOIN participants firstParticioents ON firstParticioents.thread_id = threads.id
            LEFT JOIN participants secondParticioents ON secondParticioents.thread_id = threads.id
            WHERE threads.type = 0 AND
            firstParticioents.user_id = $userId AND
            secondParticioents.user_id = $participentId
        SQL;
        if (!empty(DB::select($sql)))
            return DB::select($sql)[0];
    }

    private function makePrivateThread($user)
    {
        $thread = new Thread();
        $thread->subject = $user->name;
        $thread->picture = $user->picture;
        $thread->type = 0;
        $thread->save();

        $thread->addParticipant([Auth::user()->id, $user->id]);
        return $thread;
    }
}