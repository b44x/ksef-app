<div class="card">
    <h1>Sign in</h1>
    <form wire:submit="authenticate">
        <label for="email">Email</label>
        <input id="email" type="email" wire:model="email" autocomplete="username" autofocus>
        @error('email') <div class="error">{{ $message }}</div> @enderror

        <label for="password">Password</label>
        <input id="password" type="password" wire:model="password" autocomplete="current-password">
        @error('password') <div class="error">{{ $message }}</div> @enderror

        <label><input type="checkbox" wire:model="remember" style="width:auto"> Remember me</label>
        <button type="submit" wire:loading.attr="disabled">Sign in</button>
    </form>
</div>
