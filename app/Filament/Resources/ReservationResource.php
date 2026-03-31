<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReservationResource\Pages;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\ReservationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

class ReservationResource extends Resource
{
    protected static ?string $model = Reservation::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Reservas';

    protected static ?string $modelLabel = 'Reserva';

    protected static ?string $pluralModelLabel = 'Reservas';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('client_name')
                    ->label('Cliente')
                    ->getStateUsing(fn (Reservation $record): string => $record->user?->name ?? $record->guest_name ?? '-')
                    ->searchable(query: function ($query, string $search): void {
                        $query->where(function ($query) use ($search) {
                            $query->whereHas('user', fn ($q) => $q->where('name', 'ilike', "%{$search}%"))
                                ->orWhere('guest_name', 'ilike', "%{$search}%");
                        });
                    }),

                TextColumn::make('table.name')
                    ->label('Mesa')
                    ->sortable(),

                TextColumn::make('seats_requested')
                    ->label('Comensales')
                    ->sortable(),

                TextColumn::make('date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('start_time')
                    ->label('Inicio')
                    ->time('H:i'),

                TextColumn::make('end_time')
                    ->label('Fin')
                    ->time('H:i'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::statusLabel($state))
                    ->color(fn (string $state): string => self::statusColor($state))
                    ->sortable(),

                TextColumn::make('payment.status')
                    ->label('Pago')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::paymentLabel($state))
                    ->color(fn (?string $state): string => self::paymentColor($state)),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        Reservation::STATUS_PENDING => 'Pendiente',
                        Reservation::STATUS_CONFIRMED => 'Confirmada',
                        Reservation::STATUS_COMPLETED => 'Completada',
                        Reservation::STATUS_CANCELLED => 'Cancelada',
                        Reservation::STATUS_NO_SHOW => 'No show',
                        Reservation::STATUS_EXPIRED => 'Expirada',
                    ]),

                SelectFilter::make('payment_status')
                    ->label('Estado de pago')
                    ->relationship('payment', 'status')
                    ->options([
                        Payment::STATUS_PENDING => 'Pendiente',
                        Payment::STATUS_SUCCEEDED => 'Pagado',
                        Payment::STATUS_FAILED => 'Fallido',
                        Payment::STATUS_REFUNDED => 'Reembolsado',
                        Payment::STATUS_PARTIALLY_REFUNDED => 'Reembolso parcial',
                    ]),
            ])
            ->recordActions([
                Action::make('no_show')
                    ->label('No show')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Marcar como No show')
                    ->modalDescription('Esta accion cambiara el estado de la reserva a "No show". Esta seguro?')
                    ->visible(fn (Reservation $record): bool => $record->status === Reservation::STATUS_COMPLETED)
                    ->action(fn (Reservation $record) => app(ReservationService::class)->markAsNoShow($record)),
            ]);
    }

    private static function statusLabel(string $state): string
    {
        return match ($state) {
            Reservation::STATUS_PENDING => 'Pendiente',
            Reservation::STATUS_CONFIRMED => 'Confirmada',
            Reservation::STATUS_COMPLETED => 'Completada',
            Reservation::STATUS_CANCELLED => 'Cancelada',
            Reservation::STATUS_NO_SHOW => 'No show',
            Reservation::STATUS_EXPIRED => 'Expirada',
            default => $state,
        };
    }

    private static function statusColor(string $state): string
    {
        return match ($state) {
            Reservation::STATUS_PENDING => 'warning',
            Reservation::STATUS_CONFIRMED => 'info',
            Reservation::STATUS_COMPLETED => 'success',
            Reservation::STATUS_CANCELLED => 'gray',
            Reservation::STATUS_NO_SHOW => 'danger',
            Reservation::STATUS_EXPIRED => 'gray',
            default => 'gray',
        };
    }

    private static function paymentLabel(?string $state): string
    {
        return match ($state) {
            Payment::STATUS_PENDING => 'Pendiente',
            Payment::STATUS_SUCCEEDED => 'Pagado',
            Payment::STATUS_FAILED => 'Fallido',
            Payment::STATUS_REFUNDED => 'Reembolsado',
            Payment::STATUS_PARTIALLY_REFUNDED => 'Reembolso parcial',
            default => 'Sin pago',
        };
    }

    private static function paymentColor(?string $state): string
    {
        return match ($state) {
            Payment::STATUS_PENDING => 'warning',
            Payment::STATUS_SUCCEEDED => 'success',
            Payment::STATUS_FAILED => 'danger',
            Payment::STATUS_REFUNDED => 'info',
            Payment::STATUS_PARTIALLY_REFUNDED => 'info',
            default => 'gray',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReservations::route('/'),
        ];
    }
}
