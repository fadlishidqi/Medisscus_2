<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $token;
    public string $email;

    public function __construct(string $token, string $email)
    {
        $this->token = $token;
        $this->email = $email;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url', config('app.url'));
        $resetUrl = $frontendUrl . "/reset-password?token={$this->token}&email=" . urlencode($this->email);
        $expireTime = config('auth.passwords.users.expire', 60);
        
        // Log untuk debugging
        Log::info('Sending reset password email', [
            'user_id' => $notifiable->id,
            'email' => $this->email,
            'template' => 'emails.reset-password',
            'reset_url' => $resetUrl
        ]);
        
        try {
            return (new MailMessage)
                ->subject('🔐 Reset Password - ' . config('app.name'))
                ->view('emails.reset-password', [
                    'user' => $notifiable,
                    'resetUrl' => $resetUrl,
                    'token' => $this->token,
                    'expireTime' => $expireTime,
                    'appName' => config('app.name'),
                    'appUrl' => config('app.url'),
                    'frontendUrl' => $frontendUrl
                ]);
        } catch (\Exception $e) {
            Log::error('Error using custom email template, falling back to default', [
                'error' => $e->getMessage()
            ]);
            
            // Fallback ke template default jika ada error
            return (new MailMessage)
                ->subject('🔐 Reset Password - ' . config('app.name'))
                ->greeting('Halo ' . $notifiable->name . '!')
                ->line('Anda menerima email ini karena kami menerima permintaan reset password untuk akun Anda.')
                ->action('🔐 Reset Password', $resetUrl)
                ->line('Link reset password ini akan kedaluwarsa dalam ' . $expireTime . ' menit.')
                ->line('Jika Anda tidak melakukan permintaan reset password, tidak ada tindakan lebih lanjut yang diperlukan.')
                ->salutation('Salam, Tim ' . config('app.name'));
        }
    }
}