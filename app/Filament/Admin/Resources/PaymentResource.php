<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Billing\Actions\VerifyManualPaymentAction;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Domain\Identity\Models\User;
use App\Filament\Admin\Resources\PaymentResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance & Billing';

    protected static ?string $navigationLabel = 'Payments & Ledger';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $count = Payment::where('status', PaymentStatus::Pending->value)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Payment Ledger Transaction')
                    ->schema([
                        Forms\Components\TextInput::make('reference_number')
                            ->disabled(),
                        Forms\Components\TextInput::make('amount')
                            ->formatStateUsing(fn (?Payment $record): string => $record ? $record->getAmount()->getFormatted() : '-')
                            ->disabled(),
                        Forms\Components\Select::make('method')
                            ->options(PaymentMethod::options())
                            ->disabled(),
                        Forms\Components\Select::make('status')
                            ->options(PaymentStatus::options())
                            ->disabled(),
                        Forms\Components\Select::make('recorded_by')
                            ->relationship('recorder', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn (User $user): string => $user->full_name)
                            ->disabled(),
                        Forms\Components\Select::make('verified_by')
                            ->relationship('verifier', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn (User $user): string => $user->full_name)
                            ->disabled(),
                        Forms\Components\Textarea::make('verification_notes')
                            ->disabled(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('student.full_name')
                    ->label('Student')
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn (Payment $record): string => $record->getAmount()->getFormatted())
                    ->sortable(),

                Tables\Columns\TextColumn::make('method')
                    ->badge(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (PaymentStatus $state): string => match ($state) {
                        PaymentStatus::Verified => 'success',
                        PaymentStatus::Pending => 'warning',
                        PaymentStatus::Failed => 'danger',
                        PaymentStatus::Refunded => 'gray',
                    }),

                Tables\Columns\TextColumn::make('recorder.first_name')
                    ->label('Maker (Recorded By)')
                    ->formatStateUsing(fn (Payment $record): string => $record->recorder ? $record->recorder->full_name : ($record->gateway === 'stripe' ? 'Online Gateway' : 'System')),

                Tables\Columns\TextColumn::make('verifier.first_name')
                    ->label('Checker (Verified By)')
                    ->formatStateUsing(fn (Payment $record): string => $record->verifier ? $record->verifier->full_name : ($record->gateway === 'stripe' ? 'Stripe Webhook' : '-')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(PaymentStatus::options()),
                Tables\Filters\SelectFilter::make('method')
                    ->options(PaymentMethod::options()),
            ])
            ->actions([
                // Maker-checker verification action
                Tables\Actions\Action::make('verify')
                    ->label('Approve & Verify')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label('Verification Notes / Bank Statement Ref')
                            ->nullable(),
                    ])
                    ->visible(function (Payment $record): bool {
                        $auth = auth()->user();
                        if ($auth === null) {
                            return false;
                        }

                        // Enforce Maker-Checker:
                        // 1. Payment must be pending
                        // 2. Auth user CANNOT be the recorder
                        // 3. Auth user must have 'payment.verify'
                        return $record->status === PaymentStatus::Pending
                            && $record->recorded_by !== $auth->id
                            && $auth->can('payment.verify');
                    })
                    ->action(function (Payment $record, array $data): void {
                        /** @var User $actor */
                        $actor = auth()->user();

                        VerifyManualPaymentAction::run(
                            payment: $record,
                            checker: $actor,
                            approved: true,
                            notes: $data['notes'] ?? null
                        );

                        Notification::make()
                            ->title('Payment Verified')
                            ->body("Payment of {$record->getAmount()->getFormatted()} verified. Invoice updated.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Rejection Reason')
                            ->required(),
                    ])
                    ->visible(function (Payment $record): bool {
                        $auth = auth()->user();
                        if ($auth === null) {
                            return false;
                        }

                        return $record->status === PaymentStatus::Pending
                            && $record->recorded_by !== $auth->id
                            && $auth->can('payment.verify');
                    })
                    ->action(function (Payment $record, array $data): void {
                        /** @var User $actor */
                        $actor = auth()->user();

                        VerifyManualPaymentAction::run(
                            payment: $record,
                            checker: $actor,
                            approved: false,
                            notes: $data['reason']
                        );

                        Notification::make()
                            ->title('Payment Rejected')
                            ->danger()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
        ];
    }
}
