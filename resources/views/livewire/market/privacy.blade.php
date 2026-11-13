@php
    $email = config('hub.support_email');
    $phone = config('hub.support_phone');
@endphp
<article class="max-w-3xl mx-auto space-y-6 text-[15px] leading-relaxed text-ink-secondary">
    <header class="space-y-2">
        <h1 class="font-display text-[30px] font-bold text-ink">Privacy policy</h1>
        <p class="text-[13px] text-ink-mute">Amsterdam Digital website and mobile app · Last updated 13 November 2026</p>
    </header>

    <p>Amsterdam Digital is a marketplace for animal feeds, medicines and farm products run by Amsterdam Group in Tanzania. This policy explains what information we collect when you use the website (amsterdamdigital.co.tz) or the Amsterdam Digital app, why we need it, who sees it and how you can delete it.</p>

    <section class="space-y-2">
        <h2 class="font-display text-[20px] font-bold text-ink">What we collect</h2>
        <ul class="list-disc pl-5 space-y-1.5">
            <li><strong class="text-ink">Account details:</strong> your name, email address, phone number and password (stored encrypted). If you sign in with Google, we receive your name, email address and Google account ID from Google.</li>
            <li><strong class="text-ink">Profile photo:</strong> only if you choose to add one.</li>
            <li><strong class="text-ink">Location:</strong> the place you set to see nearby stores and distances. The app reads your device's location only when you allow it, to show how far a store is.</li>
            <li><strong class="text-ink">Orders and cart:</strong> the products you order, the store, the order reference, your contact phone and any note you leave for the store.</li>
            <li><strong class="text-ink">Questions to the AI assistant:</strong> the messages you type to Ask AI or the farm assistant.</li>
            <li><strong class="text-ink">Seller information:</strong> for businesses that sell on the platform, business and store details, staff accounts, stock, sales and the documents they upload for approval.</li>
            <li><strong class="text-ink">Technical data:</strong> standard server logs (such as IP address, browser or app version and time of request) used to keep the service secure and working.</li>
        </ul>
    </section>

    <section class="space-y-2">
        <h2 class="font-display text-[20px] font-bold text-ink">How we use it</h2>
        <ul class="list-disc pl-5 space-y-1.5">
            <li>To run your account and sign you in.</li>
            <li>To show products, prices and stores near you.</li>
            <li>To send your order to the store you chose and keep you updated on it.</li>
            <li>To answer your questions with the AI assistant and suggest products.</li>
            <li>To keep the platform safe, prevent misuse and fix problems.</li>
        </ul>
        <p>We do not sell your personal information, and we do not use it for advertising.</p>
    </section>

    <section class="space-y-2">
        <h2 class="font-display text-[20px] font-bold text-ink">Who we share it with</h2>
        <ul class="list-disc pl-5 space-y-1.5">
            <li><strong class="text-ink">The store you order from:</strong> your name, contact phone, note and the order, so they can prepare it and hand it over.</li>
            <li><strong class="text-ink">Google:</strong> when you choose "Continue with Google" to sign in.</li>
            <li><strong class="text-ink">Anthropic:</strong> our AI provider. The questions you send to the assistant, with product and store information, are processed by Anthropic's Claude to write the answer.</li>
            <li><strong class="text-ink">Hosting and map providers:</strong> our web hosting provider stores the data; maps are shown using OpenStreetMap.</li>
            <li><strong class="text-ink">Authorities:</strong> only when the law requires it.</li>
        </ul>
    </section>

    <section class="space-y-2">
        <h2 class="font-display text-[20px] font-bold text-ink">How long we keep it</h2>
        <p>We keep your account details for as long as you have an account. Order records are kept so stores can keep their business records; when you delete your account your name, phone and notes are removed from them.</p>
    </section>

    <section class="space-y-2" id="delete">
        <h2 class="font-display text-[20px] font-bold text-ink">Deleting your account</h2>
        <p>You can delete your customer account at any time: in the app go to <strong class="text-ink">Account → Delete account</strong>, or on the website go to <strong class="text-ink">My account → Delete account</strong>. Full details are on the <a href="{{ route('market.delete-account') }}" class="font-medium underline">delete your account</a> page.</p>
    </section>

    <section class="space-y-2">
        <h2 class="font-display text-[20px] font-bold text-ink">Your choices</h2>
        <ul class="list-disc pl-5 space-y-1.5">
            <li>You can see and change your name, phone, email, photo and location in your account at any time.</li>
            <li>You can turn off location access for the app in your phone's settings.</li>
            <li>You can ask us for a copy of your information or to correct it using the contact details below.</li>
        </ul>
    </section>

    <section class="space-y-2">
        <h2 class="font-display text-[20px] font-bold text-ink">Security</h2>
        <p>Data is sent over encrypted connections (HTTPS), passwords are stored encrypted, and staff only see what their role needs.</p>
    </section>

    <section class="space-y-2">
        <h2 class="font-display text-[20px] font-bold text-ink">Children</h2>
        <p>Amsterdam Digital is for farmers, livestock keepers and businesses. It is not meant for children under 13.</p>
    </section>

    <section class="space-y-2">
        <h2 class="font-display text-[20px] font-bold text-ink">Changes</h2>
        <p>If we change this policy we will update the date above and, for important changes, tell you in the app or on the website.</p>
    </section>

    <section class="space-y-2 rounded-2xl bg-canvas border border-hairline p-5">
        <h2 class="font-display text-[20px] font-bold text-ink">Contact us</h2>
        <p>Amsterdam Group, Tanzania</p>
        @if($email)<p>Email: <a href="mailto:{{ $email }}" class="font-medium underline">{{ $email }}</a></p>@endif
        @if($phone)<p>Phone: {{ $phone }}</p>@endif
        <p>Or visit any of our stores listed on <a href="{{ route('market.home') }}" class="font-medium underline">amsterdamdigital.co.tz</a>.</p>
    </section>
</article>
