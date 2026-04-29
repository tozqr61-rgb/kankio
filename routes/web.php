<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\MusicController;
use App\Http\Controllers\VoiceController;

// Landing
Route::get('/', fn() => view('landing'))->name('landing');

// Maintenance page
Route::get('/maintenance', fn() => view('maintenance'))->name('maintenance');

// Bağlantıda Kal — doğum günü sayfası
Route::get('/baglantikal', [App\Http\Controllers\BaglantiKalController::class, 'index'])->name('stay.connected');
Route::post('/baglantikal/kaydet', [App\Http\Controllers\BaglantiKalController::class, 'save'])->name('stay.save');
Route::post('/baglantikal/audio', [App\Http\Controllers\BaglantiKalController::class, 'uploadAudio'])->name('stay.audio');

// PWA offline fallback (cached by service worker)
Route::get('/offline', fn() => view('offline'))->name('pwa.offline');

// Auth
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/register', [AuthController::class, 'register'])->name('register.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Chat (authenticated)
Route::middleware('auth')->group(function () {
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/{roomId}', [ChatController::class, 'room'])->name('chat.room');

    // Polling API
    Route::get('/api/chat/{roomId}/messages', [ChatController::class, 'poll'])->name('api.poll');
    Route::post('/api/chat/{roomId}/messages', [MessageController::class, 'store'])->name('api.message.store');
    Route::delete('/api/chat/{roomId}/messages/{messageId}', [MessageController::class, 'destroy'])->name('api.message.destroy');
    Route::post('/api/chat/{roomId}/seen', [ChatController::class, 'markSeen'])->name('api.message.seen');
    Route::get('/api/chat/{roomId}/archived', [ChatController::class, 'archivedMessages'])->name('api.message.archived');

    // Rooms API
    Route::post('/api/rooms', [RoomController::class, 'store'])->name('api.room.store');
    Route::delete('/api/rooms/{roomId}', [RoomController::class, 'destroy'])->name('api.room.destroy');
    Route::get('/api/rooms/{roomId}/frame', [ChatController::class, 'roomFrame'])->name('api.room.frame');
    Route::get('/api/users', [RoomController::class, 'getUsers'])->name('api.users');

    // Presence
    Route::post('/api/presence', [ChatController::class, 'updatePresence'])->name('api.presence.update');
    Route::get('/api/presence', [ChatController::class, 'getPresence'])->name('api.presence.get');

    // Unread counts
    Route::get('/api/unread', [ChatController::class, 'unreadCounts'])->name('api.unread');

    // Profile
    Route::post('/api/profile/avatar', [ProfileController::class, 'uploadAvatar'])->name('api.profile.avatar');
    Route::post('/api/profile/notifications', [ProfileController::class, 'toggleNotifications'])->name('api.profile.notifications');

    // Music (slash-command driven)
    Route::get('/api/music/{roomId}', [MusicController::class, 'getState'])->name('api.music.state');
    Route::post('/api/music/{roomId}/command', [MusicController::class, 'handleCommand'])->name('api.music.command');

    // Voice
    Route::post('/api/voice/{roomId}/join', [VoiceController::class, 'join'])->name('api.voice.join');
    Route::post('/api/voice/{roomId}/leave', [VoiceController::class, 'leave'])->name('api.voice.leave');
    Route::post('/api/voice/{roomId}/mute', [VoiceController::class, 'toggleMute'])->name('api.voice.mute');
    Route::get('/api/voice/{roomId}/state', [VoiceController::class, 'state'])->name('api.voice.state');

    // Admin
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('admin.dashboard');
        Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
        Route::get('/rooms', [AdminController::class, 'rooms'])->name('admin.rooms');
        Route::get('/invites', [AdminController::class, 'invites'])->name('admin.invites');

        Route::post('/users/{userId}/ban', [AdminController::class, 'banUser'])->name('admin.ban');
        Route::post('/users/{userId}/role', [AdminController::class, 'toggleAdmin'])->name('admin.toggle.role');
        Route::delete('/users/{userId}', [AdminController::class, 'deleteUser'])->name('admin.user.delete');
        Route::delete('/rooms/{roomId}', [AdminController::class, 'deleteRoom'])->name('admin.room.delete');
        Route::post('/invites', [AdminController::class, 'createInvite'])->name('admin.invite.create');
        Route::delete('/invites/{id}', [AdminController::class, 'deleteInvite'])->name('admin.invite.delete');
        Route::post('/clean/old', [AdminController::class, 'cleanOldMessages'])->name('admin.clean.old');
        Route::post('/clean/all', [AdminController::class, 'cleanAllMessages'])->name('admin.clean.all');
        Route::post('/announcement', [AdminController::class, 'postAnnouncement'])->name('admin.announcement.post');
        Route::delete('/announcement', [AdminController::class, 'clearAnnouncement'])->name('admin.announcement.clear');
        Route::post('/app-release', [AdminController::class, 'postAppRelease'])->name('admin.app_release.post');
        Route::post('/maintenance', [AdminController::class, 'toggleMaintenance'])->name('admin.maintenance.toggle');
    });
});
