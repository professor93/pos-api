<?php

namespace App\Filament\Resources\Branches\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BranchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('ext_id')
                    ->required(),
                TextInput::make('address'),
                TextInput::make('phone')
                    ->tel(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
