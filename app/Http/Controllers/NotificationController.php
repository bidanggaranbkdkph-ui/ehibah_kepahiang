<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return view('notifications.index', ['items' => $request->user()->notifications()->paginate(20)]);
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    public function open(Request $request, string $id)
    {
        $n = $request->user()->notifications()->findOrFail($id);
        $n->markAsRead();
        $appId = $n->data['application_id'] ?? null;

        return $appId ? redirect()->route('applications.show', $appId) : redirect()->route('notifications.index');
    }
}
