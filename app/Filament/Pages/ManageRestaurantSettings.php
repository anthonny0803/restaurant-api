<?php

namespace App\Filament\Pages;

use App\Models\RestaurantSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;

/**
 * @property-read Schema $form
 */
class ManageRestaurantSettings extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Configuracion';

    protected static ?string $title = 'Configuracion del Restaurante';

    protected static ?int $navigationSort = 99;

    /** @var array<string, mixed> | null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->getRecord()->attributesToArray());
    }

    protected function getRecord(): RestaurantSetting
    {
        return RestaurantSetting::firstOrFail();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->model(fn () => $this->getRecord())
            ->operation('edit')
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Horario de Apertura')
                    ->schema([
                        TimePicker::make('opening_time')
                            ->label('Hora de apertura')
                            ->helperText('Hora a la que el restaurante abre para reservas.')
                            ->required()
                            ->seconds(false),

                        TimePicker::make('closing_time')
                            ->label('Hora de cierre')
                            ->helperText('Hora a la que el restaurante cierra. Las reservas deben iniciar antes de este horario.')
                            ->required()
                            ->seconds(false),
                    ])
                    ->columns(2),

                Section::make('Deposito y Pagos')
                    ->schema([
                        TextInput::make('deposit_per_person')
                            ->label('Deposito por persona')
                            ->helperText('Monto que cada comensal paga al reservar. Se descuenta del consumo final.')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->prefix('€'),
                    ])
                    ->columns(2),

                Section::make('Politica de Cancelacion')
                    ->schema([
                        TextInput::make('cancellation_deadline_hours')
                            ->label('Plazo de cancelacion')
                            ->helperText('Horas minimas de antelacion para cancelar con reembolso completo.')
                            ->required()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(168)
                            ->suffix('horas'),

                        TextInput::make('refund_percentage')
                            ->label('Porcentaje de reembolso')
                            ->helperText('Porcentaje del deposito que se devuelve si cancela fuera del plazo.')
                            ->required()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%'),

                    ])
                    ->columns(2),

                Section::make('Reservas')
                    ->schema([
                        TextInput::make('default_reservation_duration_minutes')
                            ->label('Duracion de reserva')
                            ->helperText('Tiempo que se bloquea la mesa por cada reserva.')
                            ->required()
                            ->integer()
                            ->minValue(15)
                            ->maxValue(480)
                            ->suffix('minutos'),

                        TextInput::make('reminder_hours_before')
                            ->label('Recordatorio previo')
                            ->helperText('Horas antes de la reserva para enviar el recordatorio al cliente.')
                            ->required()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(168)
                            ->suffix('horas'),

                        Select::make('time_slot_interval_minutes')
                            ->label('Intervalo de franja horaria')
                            ->helperText('Separacion entre horarios disponibles para reservar.')
                            ->required()
                            ->options([
                                15 => '15 minutos',
                                30 => '30 minutos',
                                45 => '45 minutos',
                                60 => '60 minutos',
                            ]),
                    ])
                    ->columns(2),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    protected function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment(Alignment::Start)
                    ->key('form-actions'),
            ]);
    }

    /** @return array<Action> */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Guardar cambios')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $this->getRecord()->update($data);

        Notification::make()
            ->success()
            ->title('Configuracion actualizada')
            ->send();
    }
}
