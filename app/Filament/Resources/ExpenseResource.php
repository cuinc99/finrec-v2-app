<?php

namespace App\Filament\Resources;

use Carbon\Carbon;
use Filament\Forms;
use App\Models\Type;
use Filament\Tables;
use App\Models\Expense;
use Filament\Forms\Form;
use App\Enums\TypeKeyEnum;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Awcodes\TableRepeater\Header;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\ActionSize;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Actions\Action;
use App\Filament\Resources\ExpenseResource\Pages;
use Awcodes\TableRepeater\Components\TableRepeater;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Hidden::make('user_id')
                            ->default(auth()->id())
                            ->required(),
                        Forms\Components\DatePicker::make('purchase_date')
                            ->label(__('models.expenses.fields.purchase_date'))
                            ->default(now())
                            ->maxDate(now()->addDay())
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->prefixIcon('heroicon-m-calendar-days')
                            ->required(),
                    ]),
                Forms\Components\Section::make(__('models.expenses.title'))
                    ->headerActions([
                        Action::make('reset')
                            ->modalHeading(__('models.common.reset_action_heading'))
                            ->modalDescription('__(models.common.reset_action_description).')
                            ->requiresConfirmation()
                            ->color('danger')
                            ->action(fn(Forms\Set $set) => $set('items', [])),
                    ])
                    ->schema([
                        static::getItemsRepeater(),
                    ]),
            ]);
    }

    public static function getItemsRepeater(): TableRepeater
    {
        return TableRepeater::make('items')
            ->hiddenLabel()
            ->columnSpanFull()
            ->headers([
                Header::make('item')
                    ->label(__('models.expenses.fields.item'))
                    ->markAsRequired(),
                Header::make('type')
                    ->label(__('models.expenses.fields.category'))
                    ->markAsRequired(),
                Header::make('price')
                    ->label(__('models.expenses.fields.price')),
            ])
            ->schema([
                Forms\Components\TextInput::make('item')
                    ->label(__('models.expenses.fields.item'))
                    ->required(),

                Forms\Components\Select::make('type_id')
                    ->label(__('models.expenses.fields.category'))
                    ->options(Type::where('key', TypeKeyEnum::EXPENSE->value)->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Hidden::make('key')
                            ->default(TypeKeyEnum::EXPENSE->value),
                    ])
                    ->createOptionUsing(function (array $data): int {
                        return auth()->user()->types()->create($data)->getKey();
                    }),

                Forms\Components\TextInput::make('price')
                    ->label(__('models.expenses.fields.price'))
                    ->minValue(0)
                    ->default(0)
                    ->integer()
                    ->prefix('Rp')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('purchase_date')
                    ->label(__('models.expenses.fields.purchase_date'))
                    ->date()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('item')
                    ->label(__('models.expenses.fields.item'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type.name')
                    ->label(__('models.customers.fields.type'))
                    ->badge(),
                Tables\Columns\TextColumn::make('price')
                    ->label(__('models.expenses.fields.price'))
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn(string $state): string => __('Rp. ' . number_format($state, 0, ',', '.')))
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->formatStateUsing(fn(string $state): string => __('Rp. ' . number_format($state, 0, ',', '.')))
                            ->label('Total ' . __('models.expenses.fields.price')),
                    ]),
            ])
            ->filters([
                Tables\Filters\Filter::make('purchase_date')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label(__('models.common.created_from'))
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->prefixIcon('heroicon-m-calendar-days'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label(__('models.common.created_until'))
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->prefixIcon('heroicon-m-calendar-days'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('purchase_date', '>=', $date),
                            )
                            ->when(
                                $data['created_until'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('purchase_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['created_from'] ?? null) {
                            $indicators['created_from'] = __('models.common.created_from') . ' ' . Carbon::parse($data['created_from'])->toFormattedDateString();
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators['created_until'] = __('models.common.created_until') . ' ' . Carbon::parse($data['created_until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ], layout: FiltersLayout::Modal)
            ->deferFilters()
            ->persistFiltersInSession()
            ->filtersTriggerAction(
                fn(Tables\Actions\Action $action) => $action
                    ->button()
                    ->label('Filter'),
            )
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->button()
                    ->color(Color::Red)
                    ->size(ActionSize::Small),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return static::getModel()::query()->latest('purchase_date');
    }

    public static function getLabel(): string
    {
        return __('models.expenses.title');
    }

    public static function canAccess(): bool
    {
        return auth()->user()->role->isUser() || auth()->user()->role->isFree();
    }
}
