<section class="w-full space-y-6">
    <div class="space-y-1">
        <flux:heading size="xl">{{ __('Student Admission') }}</flux:heading>
        <flux:text>{{ __('Create the student and father login accounts and enroll the student in one operation.') }}</flux:text>
    </div>

    <form wire:submit="admit" class="space-y-6">
        <flux:card class="space-y-6">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-indigo-50 p-2 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-300"><flux:icon.user /></div>
                <div><flux:heading size="lg">{{ __('Student Personal Info') }}</flux:heading><flux:text>{{ __('Identity, login credentials, and contact information.') }}</flux:text></div>
            </div>

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <flux:input wire:model="studentName" :label="__('Student name')" required autofocus />
                <flux:input wire:model="studentEmail" :label="__('Email / Login ID')" type="email" required />
                <flux:input wire:model="studentPhone" :label="__('Phone')" inputmode="tel" />
                <flux:input wire:model="dateOfBirth" :label="__('Date of birth')" type="date" required />
                <flux:select wire:model="gender" :label="__('Gender')" required>
                    <flux:select.option value="">{{ __('Select gender') }}</flux:select.option>
                    @foreach (\App\Enums\Gender::cases() as $genderOption)
                        <flux:select.option :value="$genderOption->value">{{ $genderOption->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="bloodGroup" :label="__('Blood group')">
                    <flux:select.option value="">{{ __('Not specified') }}</flux:select.option>
                    @foreach (\App\Enums\BloodGroup::cases() as $bloodGroupOption)
                        <flux:select.option :value="$bloodGroupOption->value">{{ $bloodGroupOption->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div class="md:col-span-2 xl:col-span-3">
                    <flux:input wire:model="photo" :label="__('Student photo')" type="file" accept="image/jpeg,image/png,image/webp" />
                    <flux:text class="mt-2">{{ __('JPG, PNG, or WebP. Maximum 2 MB.') }}</flux:text>
                    <div wire:loading wire:target="photo" class="mt-2 text-sm text-indigo-600 dark:text-indigo-300">{{ __('Uploading photo…') }}</div>
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-6">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-sky-50 p-2 text-sky-600 dark:bg-sky-950 dark:text-sky-300"><flux:icon.academic-cap /></div>
                <div><flux:heading size="lg">{{ __('Academic Info') }}</flux:heading><flux:text>{{ __('Choose the academic placement and roll number.') }}</flux:text></div>
            </div>

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <flux:select wire:model="academicSessionId" :label="__('Academic year')" required>
                    <flux:select.option value="">{{ __('Select academic year') }}</flux:select.option>
                    @foreach ($this->academicSessions as $session)
                        <flux:select.option :value="$session->id">{{ $session->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="schoolClassId" :label="__('Class')" required>
                    <flux:select.option value="">{{ __('Select class') }}</flux:select.option>
                    @foreach ($this->classes as $schoolClass)
                        <flux:select.option :value="$schoolClass->id">{{ $schoolClass->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="shiftId" :label="__('Shift')" required>
                    <flux:select.option value="">{{ __('Select shift') }}</flux:select.option>
                    @foreach ($this->shifts as $shift)
                        <flux:select.option :value="$shift->id">{{ $shift->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="sectionId" :label="__('Section')" :disabled="! $schoolClassId || ! $shiftId" required>
                    <flux:select.option value="">{{ $schoolClassId && $shiftId ? __('Select section') : __('Select class and shift first') }}</flux:select.option>
                    @foreach ($this->sections as $section)
                        <flux:select.option :value="$section->id">{{ $section->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="classRoll" :label="__('Roll number')" required />
            </div>
        </flux:card>

        <flux:card class="space-y-6">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-emerald-50 p-2 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-300"><flux:icon.user-group /></div>
                <div><flux:heading size="lg">{{ __('Parent Info') }}</flux:heading><flux:text>{{ __('An existing father account will be reused when the phone or email matches.') }}</flux:text></div>
            </div>

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <flux:input wire:model="fatherName" :label="__(\"Father's name\")" required />
                <flux:input wire:model="fatherPhone" :label="__('Father phone')" inputmode="tel" required />
                <flux:input wire:model="fatherEmail" :label="__('Father email / Login ID')" type="email" />
            </div>
        </flux:card>

        <flux:callout icon="key" color="amber">
            <flux:callout.heading>{{ __('First-login password') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Both new accounts receive the configured admission password and must change it after signing in.') }}</flux:callout.text>
        </flux:callout>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="admit,photo">
                <span wire:loading.remove wire:target="admit">{{ __('Admit student') }}</span>
                <span wire:loading wire:target="admit">{{ __('Processing admission…') }}</span>
            </flux:button>
        </div>
    </form>
</section>
