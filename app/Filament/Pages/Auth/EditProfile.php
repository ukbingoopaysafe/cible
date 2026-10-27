<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getAvatarFormComponent(),
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }

    protected function getAvatarFormComponent(): Component
    {
        return FileUpload::make('avatar_url')
            ->label('الصورة الشخصية')
            ->avatar()
            ->image()
            ->disk('public')
            ->directory('avatars')
            ->visibility('public')
            ->maxSize(2048)
            ->imageEditor()
            ->circleCropper()
            ->helperText('يمكنك رفع صورة شخصية بصيغة JPG أو PNG أو WEBP بحجم أقصى 2 ميجابايت.');
    }

    protected function getNameFormComponent(): Component
    {
        $isManager = (bool) auth()->user()?->is_company_manager;

        return parent::getNameFormComponent()
            ->disabled(! $isManager)
            ->helperText(! $isManager ? 'تعديل الاسم متاح لمدير الشركة فقط.' : null);
    }

    protected function getEmailFormComponent(): Component
    {
        $isManager = (bool) auth()->user()?->is_company_manager;

        return parent::getEmailFormComponent()
            ->disabled(! $isManager)
            ->helperText(! $isManager ? 'تعديل البريد الإلكتروني متاح لمدير الشركة فقط.' : null);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();

        if (! ($user?->is_company_manager ?? false)) {
            unset($data['name'], $data['email']);
        }

        return parent::mutateFormDataBeforeSave($data);
    }
}
