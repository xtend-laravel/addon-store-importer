<div class="lg:gap-8 lg:flex lg:items-start">
    <div class="space-y-6 lg:flex-1">
        <div class="space-y-4">
            <div class="shadow sm:rounded-md">
                <div class="flex-col px-4 py-5 space-y-4 bg-white sm:p-6">
                    @foreach($schema as $field)
                        @include('adminhub::partials.form-field')
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
