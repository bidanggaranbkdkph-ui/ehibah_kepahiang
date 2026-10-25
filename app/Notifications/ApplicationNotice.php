<?php

namespace App\Notifications;

use App\Models\GrantApplication;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi in-app (tabel notifications) + surel. Dipakai untuk perubahan status,
 * pengingat LPJ, surat peringatan, dan koreksi data.
 */
class ApplicationNotice extends Notification
{
    public function __construct(
        public GrantApplication $application,
        public string $title,
        public string $message,
        public string $level = 'info', // info | success | warning | danger
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[E-Hibah Kepahiang] '.$this->title)
            ->greeting('Yth. '.$notifiable->name)
            ->line($this->message)
            ->line('Usulan: '.($this->application->reg_number ?? '(draf)').' — '.$this->application->title)
            ->action('Buka usulan', route('applications.show', $this->application))
            ->line('Pesan otomatis dari aplikasi E-Hibah Pemerintah Kabupaten Kepahiang.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'application_id' => $this->application->id,
            'reg_number' => $this->application->reg_number,
            'title' => $this->title,
            'message' => $this->message,
            'level' => $this->level,
            'status' => $this->application->status->value,
            'url' => route('applications.show', $this->application),
        ];
    }
}
