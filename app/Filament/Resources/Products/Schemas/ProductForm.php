<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Product;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Listing')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, callable $set, ?Product $record): void {
                                // Slugs stay put once a product exists —
                                // changing one breaks storefront links.
                                if ($record === null && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Used in the storefront URL.'),

                        Select::make('type')
                            ->options(collect(ProductType::cases())
                                ->mapWithKeys(fn (ProductType $type) => [$type->value => $type->label()])
                                ->all())
                            ->required(),

                        Select::make('category_id')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->label('Category'),

                        TextInput::make('summary')
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->helperText('One line shown on the storefront card.'),

                        Textarea::make('description')
                            ->rows(8)
                            ->columnSpanFull(),
                    ]),

                Section::make('Pricing & visibility')
                    ->columns(3)
                    ->schema([
                        TextInput::make('price')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required()
                            ->helperText('In cents. 0 makes the product free.'),

                        Select::make('status')
                            ->options([
                                ProductStatus::Draft->value => 'Draft',
                                ProductStatus::Published->value => 'Published',
                                ProductStatus::Archived->value => 'Archived',
                            ])
                            ->default(ProductStatus::Draft->value)
                            ->required()
                            ->helperText('Only published products with a release are purchasable.'),

                        Toggle::make('featured')
                            ->helperText('Pin to the top of the storefront.'),
                    ]),

                Section::make('Releases')
                    ->description('Buyers download these. Files are stored privately and only served through signed, authorized links.')
                    ->schema([
                        Repeater::make('versions')
                            ->relationship()
                            ->hiddenLabel()
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Add a release')
                            ->itemLabel(fn (array $state): ?string => $state['version'] ?? null)
                            ->schema([
                                TextInput::make('version')
                                    ->required()
                                    ->maxLength(20)
                                    ->placeholder('1.0.0'),

                                DateTimePicker::make('released_at')
                                    ->default(now()),

                                FileUpload::make('file_path')
                                    ->required()
                                    ->disk(config('marketplace.releases_disk'))
                                    ->directory('releases')
                                    ->visibility('private')
                                    ->columnSpanFull()
                                    ->afterStateUpdated(function ($state, callable $set): void {
                                        if ($state instanceof UploadedFile) {
                                            $set('file_size', $state->getSize());
                                        }
                                    }),

                                Textarea::make('changelog')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Section::make('Screenshots')
                    ->schema([
                        Repeater::make('images')
                            ->relationship()
                            ->hiddenLabel()
                            ->defaultItems(0)
                            ->addActionLabel('Add a screenshot')
                            ->orderColumn('position')
                            ->schema([
                                FileUpload::make('path')
                                    ->required()
                                    ->image()
                                    ->disk(config('marketplace.images_disk'))
                                    ->directory('product-images')
                                    ->visibility('public'),
                            ]),
                    ]),
            ]);
    }
}
