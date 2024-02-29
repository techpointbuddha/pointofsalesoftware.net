<?php

namespace Modules\Crm\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderRequestNotification extends Notification
{
    use Queueable;

    protected $invoice;
    protected $user_name;


    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($invoice,$user_name)
    {
        $this->invoice = $invoice;
        $this->user_name = $user_name;
    }
    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {

    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'invoice_no' => $this->invoice->invoice_no,
            'user_name' => $this->user_name,
        ];
    }
}
