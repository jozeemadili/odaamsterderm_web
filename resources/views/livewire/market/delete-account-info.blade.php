@php
    $email = config('hub.support_email');
    $phone = config('hub.support_phone');
@endphp
<article class="max-w-3xl mx-auto space-y-6 text-[15px] leading-relaxed text-ink-secondary">
    <header class="space-y-2">
        <h1 class="font-display text-[30px] font-bold text-ink">Delete your Amsterdam Digital account</h1>
        <p>Futa akaunti yako ya Amsterdam Digital.</p>
    </header>

    <section class="space-y-2">
        <h2 class="font-display text-[20px] font-bold text-ink">In the app</h2>
        <ol class="list-decimal pl-5 space-y-1.5">
            <li>Open the Amsterdam Digital app and sign in.</li>
            <li>Tap <strong class="text-ink">Account</strong> at the bottom.</li>
            <li>Scroll down and tap <strong class="text-ink">Delete account</strong>.</li>
            <li>Enter your password (or type DELETE if you signed up with Google) and confirm.</li>
        </ol>
    </section>

    <section class="space-y-2">
        <h2 class="font-display text-[20px] font-bold text-ink">On the website</h2>
        <p>Sign in, open <a href="{{ route('market.account') }}" class="font-medium underline">My account</a> and use <strong class="text-ink">Delete account</strong> at the bottom of the page.</p>
    </section>

    <section class="space-y-2">
        <h2 class="font-display text-[20px] font-bold text-ink">What is deleted and what is kept</h2>
        <ul class="list-disc pl-5 space-y-1.5">
            <li><strong class="text-ink">Deleted straight away:</strong> your name, email, phone, password, Google sign-in link, profile photo, saved location, cart and notifications. You are signed out on every device.</li>
            <li><strong class="text-ink">Cancelled:</strong> orders the shop has not started preparing.</li>
            <li><strong class="text-ink">Kept without your details:</strong> past order records, which stores need for their business records. Your name, phone and notes are removed from them. An order a shop is already preparing keeps your phone number until it is handed over.</li>
        </ul>
    </section>

    <section class="space-y-2">
        <h2 class="font-display text-[20px] font-bold text-ink">Sellers and staff</h2>
        <p>Business, seller and staff accounts are closed by our support team, so that store records are handled properly. Contact us below.</p>
    </section>

    <section class="space-y-2 rounded-2xl bg-canvas border border-hairline p-5">
        <h2 class="font-display text-[20px] font-bold text-ink">Can't sign in?</h2>
        <p>Ask us to delete your account and we will confirm by email or phone.</p>
        @if($email)<p>Email: <a href="mailto:{{ $email }}?subject=Delete%20my%20Amsterdam%20Digital%20account" class="font-medium underline">{{ $email }}</a></p>@endif
        @if($phone)<p>Phone: {{ $phone }}</p>@endif
        <p>Read our <a href="{{ route('market.privacy') }}" class="font-medium underline">privacy policy</a>.</p>
    </section>
</article>
