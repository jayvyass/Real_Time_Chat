<?php
use App\Events\MessageSent;
use App\Events\MessageSeen;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        $users = User::whereNot('id', auth()->id())->get();

        foreach ($users as $user) {
            $latestMessage = ChatMessage::where(function ($query) use ($user) {
                $query->where('sender_id', auth()->id())
                      ->where('receiver_id', $user->id)
                      ->where('status','active');
            })->orWhere(function ($query) use ($user) {
                $query->where('sender_id', $user->id)
                      ->where('receiver_id', auth()->id())
                      ->where('status','active');
            })->latest()->first();

            if ($latestMessage) {
                $createdAt = Carbon::parse($latestMessage->created_at)->setTimezone('Asia/Kolkata');
                if ($createdAt->isYesterday()) {
                    $latestMessage->formatted_time = $createdAt->format('D'); // Day of the week in three letters
                } else {
                    $latestMessage->formatted_time = $createdAt->format('H:i'); // Time in HH:mm format
                }
            }
            $user->latest_message = $latestMessage;
        }

        return view('dashboard', [
            'users' => $users,
        ]);
    })->name('dashboard');

    Route::get('/chat/{friend}', function (User $friend) {
        return view('chat', [
            'friend' => $friend,
            'users' => User::whereNot('id', auth()->id())->get()
        ]);
    })->name('chat');

    Route::get('/api/user/{id}', function ($id) {
        return User::findOrFail($id);
    });

    Route::get('/messages/{friend}', function (User $friend) {
        return ChatMessage::query()
            ->where(function ($query) use ($friend) {
                $query->where('sender_id', auth()->id())
                    ->where('receiver_id', $friend->id)
                    ->where('status','active');
            })
            ->orWhere(function ($query) use ($friend) {
                $query->where('sender_id', $friend->id)
                    ->where('receiver_id', auth()->id())
                    ->where('status','active');
            })
            ->with(['sender', 'receiver'])
            ->orderBy('id', 'asc')
            ->get();
    });

    Route::post('/messages/{friend}', function (User $friend) {
        $message = ChatMessage::create([
            'sender_id' => auth()->id(),
            'receiver_id' => $friend->id,
            'text' => request()->input('message')
        ]);

        broadcast(new MessageSent($message))->toOthers();

        return $message;
    });

    Route::post('/messages/{friend}/upload', function (Request $request, User $friend) {
        $request->validate([
            'image' => 'required|image|max:2048',
        ]);

        $path = $request->file('image')->store('chat_images', 'public');

        $message = ChatMessage::create([
            'sender_id' => auth()->id(),
            'receiver_id' => $friend->id,
            'text' => '',
            'image' => $path,
        ]);

        broadcast(new MessageSent($message))->toOthers();

        return response()->json($message);
    });

    Route::get('/api/users', function () {
        $users = User::whereNot('id', auth()->id())->get();

        foreach ($users as $user) {
            $latestMessage = ChatMessage::where(function ($query) use ($user) {
                $query->where('sender_id', auth()->id())
                      ->where('receiver_id', $user->id)
                      ->where('status','active');
            })->orWhere(function ($query) use ($user) {
                $query->where('sender_id', $user->id)
                      ->where('receiver_id', auth()->id())
                      ->where('status','active');
            })->latest()->first();

            if ($latestMessage) {
                $createdAt = Carbon::parse($latestMessage->created_at)->setTimezone('Asia/Kolkata');
                if ($createdAt->isYesterday()) {
                    $latestMessage->formatted_time = $createdAt->format('D'); // Day of the week in three letters
                } else {
                    $latestMessage->formatted_time = $createdAt->format('H:i'); // Time in HH:mm format
                }
            }
            $notificationCount = ChatMessage::where('receiver_id', auth()->id())
                ->where('sender_id', $user->id)
                ->where('mstatus', 'delivered')
                ->count();

            $user->notification_count = $notificationCount;
            $user->profile_picture_url = asset($user->photo);
            $user->latest_message = $latestMessage;
        }

        return $users;
    });

    Route::post('/messages/{friend}/seen', function (User $friend) {
        ChatMessage::where('receiver_id', auth()->id())
            ->where('sender_id', $friend->id)
            ->where('mstatus', 'delivered')
            ->update(['mstatus' => 'seen']);

        broadcast(new MessageSeen($friend->id, auth()->id()))->toOthers();

        return response()->json(['status' => 'success']);
    });

    Route::delete('/messages/{friend}', function (User $friend) {
        ChatMessage::where(function ($query) use ($friend) {
            $query->where('sender_id', auth()->id())
                ->where('receiver_id', $friend->id)
                ->where('status', 'active');
        })->orWhere(function ($query) use ($friend) {
            $query->where('sender_id', $friend->id)
                ->where('receiver_id', auth()->id())
                ->where('status', 'active');
        })->update(['status' => 'away']);

        // Broadcast message indicating chat deletion
        $message = ChatMessage::create([
            'sender_id' => auth()->id(),
            'receiver_id' => $friend->id,
            'status' => 'away'
        ]);

        broadcast(new MessageSent($message))->toOthers();

        return response()->json(['status' => 'success']);
    });
});

require __DIR__ . '/auth.php';
