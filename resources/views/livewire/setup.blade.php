<div class="card">
    <h1>Welcome</h1>
    <p>Create the administrator account. This screen exists only until the first account is created.</p>
    <form wire:submit="create">
        <label for="name">Name</label>
        <input id="name" type="text" wire:model="name" autocomplete="name" autofocus>
        @error('name') <div class="error">{{ $message }}</div> @enderror

        <label for="email">Email</label>
        <input id="email" type="email" wire:model="email" autocomplete="username">
        @error('email') <div class="error">{{ $message }}</div> @enderror

        <label for="password">Password</label>
        <input id="password" type="password" wire:model="password" autocomplete="new-password">
        <div class="hint">At least 12 characters. This account can see and send your invoices.</div>
        @error('password') <div class="error">{{ $message }}</div> @enderror

        <label for="password_confirmation">Repeat the password</label>
        <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password">

        <button type="submit" wire:loading.attr="disabled">Create the account</button>
    </form>
</div>
