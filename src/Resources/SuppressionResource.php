<?php

namespace Backstage\FilamentMails\Resources;

use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Backstage\FilamentMails\Resources\SuppressionResource\Pages\ListSuppressions;
use Backstage\Mails\Enums\EventType;
use Backstage\Mails\Enums\Provider;
use Backstage\Mails\Events\MailUnsuppressed;
use Backstage\Mails\Models\MailEvent;

class SuppressionResource extends Resource
{
    protected static bool $isScopedToTenant = false;

    protected static bool $shouldRegisterNavigation = true;

    public static function getSlug(): string
    {
        return config('filament-mails.resources.mail')::getSlug() . '/suppressions';
    }

    public static function getModel(): string
    {
        return config('mails.models.event');
    }

    public function getTitle(): string
    {
        return __('Suppressions');
    }

    public static function getNavigationParentItem(): ?string
    {
        return config('filament-mails.resources.mail')::getNavigationLabel();
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-mails.resources.mail')::getNavigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return __('Suppressions');
    }

    public static function getLabel(): ?string
    {
        return __('Suppression');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Suppressions');
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-no-symbol';
    }

    public static function getEloquentQuery(): Builder
    {
        $mailTable = config('mails.database.tables.mails');
        $eventTable = config('mails.database.tables.events');

        return parent::getEloquentQuery()
            ->from("$eventTable as events")
            ->join("$mailTable as mails", 'events.mail_id', '=', 'mails.id')
            ->where(function ($query) {
                $query->where('events.type', EventType::HARD_BOUNCED)
                    ->orWhere('events.type', EventType::COMPLAINED);
            })
            ->whereNull('events.unsuppressed_at')
            ->whereIn('mails.to', function ($query) use ($eventTable) {
                $query->select('to')
                    ->from($eventTable)
                    ->where('type', EventType::HARD_BOUNCED)
                    ->whereNull('unsuppressed_at')
                    ->groupBy('to');
            })
            ->select('events.*', 'mails.to')
            ->addSelect([
                'has_complained' => MailEvent::select('m.id')
                    ->from("$eventTable as me")
                    ->leftJoin("$mailTable as m", function ($join) {
                        $join->on('me.mail_id', '=', 'm.id')
                            ->where('me.type', '=', EventType::COMPLAINED);
                    })
                    ->take(1),
            ])
            ->latest('events.occurred_at')
            ->orderBy('events.occurred_at', 'desc');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('to')
                    ->label(__('Email address'))
                    ->formatStateUsing(fn ($record) => key(json_decode($record->to ?? [])))
                    ->searchable(['to']),

                Tables\Columns\TextColumn::make('id')
                    ->label(__('Reason'))
                    ->badge()
                    ->formatStateUsing(fn ($record) => $record->type->value == EventType::COMPLAINED->value ? 'Complained' : 'Bounced')
                    ->color(fn ($record): string => match ($record->type->value == EventType::COMPLAINED->value) {
                        true => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('occurred_at')
                    ->label(__('Occurred At'))
                    ->dateTime('d-m-Y H:i')
                    ->since()
                    ->tooltip(fn (MailEvent $record) => $record->occurred_at->format('d-m-Y H:i'))
                    ->sortable()
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\Action::make('unsuppress')
                    ->label(__('Unsuppress'))
                    ->action(function (MailEvent $record) {
                        event(new MailUnsuppressed(key($record->mail->to), $record->mail->mailer == 'smtp' && filled($record->mail->transport) ? $record->mail->transport : $record->mail->mailer, $record->mail->stream_id ?? null));
                    })
                    ->visible(fn ($record) => Provider::tryFrom($record->mail->mailer == 'smtp' && filled($record->mail->transport) ? $record->mail->transport : $record->mail->mailer)),

                Tables\Actions\ViewAction::make()
                    ->url(null)
                    ->modal()
                    ->slideOver()
                    ->label(__('View'))
                    ->hiddenLabel()
                    ->tooltip(__('View'))
                    ->infolist(fn (Infolist $infolist) => EventResource::infolist($infolist)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppressions::route('/'),
        ];
    }
}
