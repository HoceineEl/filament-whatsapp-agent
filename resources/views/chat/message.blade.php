@php
    use HoceineEl\WhatsAppAgent\Enums\MessageAuthor;
    use HoceineEl\WhatsAppAgent\Enums\MessageStatus;
    use HoceineEl\WhatsAppAgent\Enums\MessageType;
    $author = $message->author;
    $time = $message->created_at->setTimezone($timezone)->format('g:i A');
    $mediaBadge = match ($message->type) {
        MessageType::Audio => ['heroicon-m-microphone', 'whatsapp-agent::inbox.voice_note'],
        MessageType::Image => ['heroicon-m-photo', 'whatsapp-agent::inbox.photo'],
        MessageType::Document => ['heroicon-m-document-text', 'whatsapp-agent::inbox.document'],
        MessageType::Video => ['heroicon-m-video-camera', 'whatsapp-agent::inbox.video'],
        default => null,
    };
@endphp
@if ($author === MessageAuthor::System && blank($message->payload['buttons'] ?? null) && ($message->meta['kind'] ?? null) === null)
    <div class="wa-msg wa-msg--note"><span>{{ $message->body }}</span></div>
@else
    <div @class(['wa-msg', 'wa-msg--in' => $message->isInbound(), 'wa-msg--out' => ! $message->isInbound(), "wa-msg--{$author->value}"])>
        <div class="wa-msg__bubble">
            @if (! $message->isInbound())
                <span class="wa-msg__who">
                    @if ($author === MessageAuthor::Bot)
                        <x-filament::icon icon="heroicon-m-sparkles" class="wa-msg__who-icon" />
                    @endif
                    {{ $author === MessageAuthor::Staff && $message->user ? $message->user->name : $author->getLabel() }}
                </span>
            @endif
            @if ($mediaBadge)
                <span class="wa-msg__media">
                    <x-filament::icon :icon="$mediaBadge[0]" class="wa-msg__media-icon" />
                    {{ __($mediaBadge[1]) }}
                </span>
            @endif
            @if (filled($message->body))
                <p class="wa-msg__text" dir="auto">{!! nl2br(preg_replace('/\*(.+?)\*/u', '<strong>$1</strong>', e($message->body))) !!}</p>
            @endif
            @if (filled($message->payload['buttons'] ?? null))
                <div class="wa-msg__buttons">
                    @foreach ($message->payload['buttons'] as $button)
                        <span class="wa-msg__button">{{ $button['title'] }}</span>
                    @endforeach
                </div>
            @endif
            <span class="wa-msg__meta">
                @if (filled($message->meta['tools'] ?? null))
                    <span class="wa-msg__tools" title="{{ implode(', ', $message->meta['tools']) }}">
                        <x-filament::icon icon="heroicon-m-bolt" class="wa-msg__tools-icon" />
                        {{ \HoceineEl\WhatsAppAgent\WhatsAppAgent::toolSummary($message->meta['tools']) }}
                    </span>
                @endif
                <time>{{ $time }}</time>
                @unless ($message->isInbound())
                    @if ($message->status === MessageStatus::Failed)
                        <x-filament::icon icon="heroicon-m-exclamation-circle" class="wa-msg__status wa-msg__status--failed" :title="$message->error" />
                    @elseif (in_array($message->status, [MessageStatus::Delivered, MessageStatus::Read], true))
                        <span @class(['wa-msg__ticks', 'is-read' => $message->status === MessageStatus::Read]) aria-label="{{ $message->status->getLabel() }}">✓✓</span>
                    @elseif ($message->status === MessageStatus::Sent)
                        <span class="wa-msg__ticks" aria-label="{{ $message->status->getLabel() }}">✓</span>
                    @else
                        <x-filament::icon icon="heroicon-m-clock" class="wa-msg__status" />
                    @endif
                @endunless
            </span>
        </div>
    </div>
@endif
