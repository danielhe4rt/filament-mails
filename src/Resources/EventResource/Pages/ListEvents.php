<?php

namespace Backstage\FilamentMails\Resources\EventResource\Pages;

// use App\Filament\Widgets\MailsPerStatusChart;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Backstage\FilamentMails\Resources\EventResource;
use Backstage\Mails\Enums\EventType;
use Backstage\Mails\Models\MailEvent;

class ListEvents extends ListRecords
{
    public static function getResource(): string
    {
        return config('filament-mails.resources.event', EventResource::class);
    }

    public function getTitle(): string
    {
        return __('Events');
    }

    protected function getActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make()
                ->label(__('All'))
                ->badgeColor('primary')
                ->icon('heroicon-o-inbox')
                ->badge(MailEvent::count()),

            'delivered' => Tab::make()
                ->label(__('Delivered'))
                ->badgeColor('success')
                ->icon('heroicon-o-inbox-arrow-down')
                ->badge(MailEvent::where('type', EventType::DELIVERED)->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', EventType::DELIVERED)),

            'opened' => Tab::make()
                ->label(__('Opened'))
                ->badgeColor('info')
                ->icon('heroicon-o-envelope-open')
                ->badge(MailEvent::where('type', EventType::OPENED)->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', EventType::OPENED)),

            'clicked' => Tab::make()
                ->label(__('Clicked'))
                ->badgeColor('clicked')
                ->icon('heroicon-o-cursor-arrow-rays')
                ->badge(MailEvent::where('type', EventType::CLICKED)->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', EventType::CLICKED)),

            'bounced' => Tab::make()
                ->label(__('Bounced'))
                ->badgeColor('danger')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->badge(fn () => MailEvent::where('type', EventType::SOFT_BOUNCED)->count() + MailEvent::where('type', EventType::HARD_BOUNCED)->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where(function ($query) {
                    $query->where('type', EventType::SOFT_BOUNCED)
                        ->orWhere('type', EventType::HARD_BOUNCED);
                })),

            'complained' => Tab::make()
                ->label(__('Complained'))
                ->badgeColor('warning')
                ->icon('heroicon-o-face-frown')
                ->badge(MailEvent::where('type', EventType::COMPLAINED)->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', EventType::COMPLAINED)),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // MailsPerStatusChart::class,
        ];
    }
}
