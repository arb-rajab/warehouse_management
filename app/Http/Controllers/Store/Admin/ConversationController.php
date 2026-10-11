<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterConversationsRequest;
use App\Http\Requests\Store\Admin\ReplyConversationRequest;
use App\Http\Resources\Store\ConversationResource;
use App\Http\Resources\Store\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_all_product_conversations', only: ['index', 'show']),
            new Middleware('permission:reply_to_product_conversations', only: ['reply']),
            new Middleware('permission:delete_product_conversations', only: ['destroy']),
        ];
    }

    public function index(FilterConversationsRequest $request): Response
    {
        $enabled = (bool) get_setting('conversation_system');
        $perPage = $this->resolvePerPage($request, 20);

        return Inertia::render('Store/Admin/Support/Conversations/Index', [
            'conversations' => $this->paginated(ConversationResource::collection(
                Conversation::query()
                    ->select(['id', 'title', 'sender_id', 'receiver_id', 'sender_viewed', 'receiver_viewed', 'created_at'])
                    ->with(['sender:id,name', 'receiver:id,name'])
                    ->when(! $enabled, fn ($query) => $query->whereRaw('1 = 0'))
                    ->orderByDesc('created_at')->orderByDesc('id')
                    ->paginate($perPage)->withQueryString()
            )),
            'filters' => ['per_page' => $perPage],
            'enabled' => $enabled,
        ]);
    }

    public function show(FilterConversationsRequest $request, Conversation $conversation): Response
    {
        $conversation->load(['sender:id,name', 'receiver:id,name,user_type']);
        $userId = $request->user('store')->id;
        if ($conversation->sender_id === $userId) {
            $conversation->sender_viewed = 1;
        } elseif ($conversation->receiver_id === $userId) {
            $conversation->receiver_viewed = 1;
        }
        $conversation->save();
        $perPage = $this->resolvePerPage($request, 20);
        $messages = $conversation->messages()->select(['id', 'conversation_id', 'user_id', 'message', 'created_at'])
            ->with('user:id,name')->orderByDesc('id')->paginate($perPage)->withQueryString();
        // Open at the newest history page, displayed in chronological order.
        // A successful reply therefore stays visible after returning to the thread.
        $messages->setCollection($messages->getCollection()->reverse()->values());

        return Inertia::render('Store/Admin/Support/Conversations/Show', [
            'conversation' => new ConversationResource($conversation),
            'messages' => $this->paginated(MessageResource::collection($messages)),
            'filters' => ['per_page' => $perPage],
            'canReply' => $conversation->receiver?->user_type === 'admin',
        ]);
    }

    public function reply(ReplyConversationRequest $request, Conversation $conversation): RedirectResponse
    {
        $conversation->load('receiver:id,user_type');
        abort_unless($conversation->receiver?->user_type === 'admin', 403);

        DB::transaction(function () use ($request, $conversation) {
            $message = new Message;
            $message->conversation_id = $conversation->id;
            $message->user_id = $request->user('store')->id;
            $message->message = clean_html($request->validated('message'));
            $message->save();

            if ($conversation->sender_id === $message->user_id) {
                $conversation->receiver_viewed = 1;
            } elseif ($conversation->receiver_id === $message->user_id) {
                $conversation->sender_viewed = 1;
            }
            $conversation->save();
        });

        return redirect()->route('conversations.admin_show', $conversation);
    }

    public function destroy(Request $request, Conversation $conversation): RedirectResponse
    {
        DB::transaction(function () use ($conversation) {
            $conversation->messages()->delete();
            $conversation->delete();
        });

        return $this->redirectPreservingQuery('conversations.admin_index', $request);
    }
}
