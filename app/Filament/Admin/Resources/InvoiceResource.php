<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Billing\Enums\InvoicePaymentStatus;
use App\Domain\Billing\Models\Invoice;
use App\Filament\Admin\Resources\InvoiceResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Finance & Billing';

    protected static ?string $navigationLabel = 'Tax Invoices';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Invoice Information')
                    ->schema([
                        Forms\Components\TextInput::make('invoice_number')
                            ->disabled(),
                        Forms\Components\TextInput::make('financial_year')
                            ->disabled(),
                        Forms\Components\TextInput::make('billing_name')
                            ->disabled(),
                        Forms\Components\TextInput::make('billing_email')
                            ->disabled(),
                        Forms\Components\Placeholder::make('total_display')
                            ->label('Total Value')
                            ->content(fn (?Invoice $record): string => $record ? $record->getTotal()->getFormatted() : '-'),
                        Forms\Components\Placeholder::make('paid_display')
                            ->label('Amount Paid')
                            ->content(fn (?Invoice $record): string => $record ? $record->getPaidAmount()->getFormatted() : '-'),
                        Forms\Components\Placeholder::make('balance_display')
                            ->label('Balance Due')
                            ->content(fn (?Invoice $record): string => $record ? $record->getRemainingBalance()->getFormatted() : '-'),
                        Forms\Components\Select::make('status')
                            ->options(InvoicePaymentStatus::options())
                            ->disabled(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('billing_name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->formatStateUsing(fn (Invoice $record): string => $record->getTotal()->getFormatted())
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Paid')
                    ->formatStateUsing(fn (Invoice $record): string => $record->getPaidAmount()->getFormatted()),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (InvoicePaymentStatus $state): string => match ($state) {
                        InvoicePaymentStatus::Paid => 'success',
                        InvoicePaymentStatus::PartiallyPaid => 'warning',
                        InvoicePaymentStatus::Pending => 'danger',
                        InvoicePaymentStatus::Refunded => 'gray',
                        InvoicePaymentStatus::Failed => 'danger',
                    }),

                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('issued_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(InvoicePaymentStatus::options()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('print')
                    ->label('Print GST')
                    ->icon('heroicon-o-printer')
                    ->url(fn (Invoice $record): string => route('invoices.view', ['invoice' => $record->ulid]))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'view' => Pages\ViewInvoice::route('/{record}'),
        ];
    }
}
