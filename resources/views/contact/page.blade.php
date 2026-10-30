{{--
    /contactus/: the contact form of v3 (ContactController, App\Fec\Contact\ContactForm).
    One form; the topic decides the mailbox (config fec.contact.topics).
--}}
@extends('layouts.fecx')

@section('title', 'Contact Us - FreeExtraChips')
@section('meta_description', 'Contact the FreeExtraChips editorial team: corrections to a review or bonus, tips, press, partnerships, payments, privacy requests and website problems.')
@section('canonical', $canonical)

@push('head')
<style>
.fec-contact { max-width: 1040px; margin: 0 auto; padding: 8px 0 40px; }
.fec-contact h1 { margin: 0 0 8px; }
.fec-contact .fc-lede { color: #555; max-width: 720px; margin: 0 0 26px; }
.fec-contact .fc-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 32px; align-items: start; }
.fec-contact .fc-card { border: 1px solid #e3e3e3; border-radius: 10px; background: #fdfdfd; padding: 22px 22px 8px; }
.fec-contact .fc-row { margin-bottom: 16px; }
.fec-contact .fc-row--two { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.fec-contact label { display: block; font-weight: 600; margin-bottom: 5px; font-size: .94em; }
.fec-contact .fc-req { color: #c0392b; }
.fec-contact input[type=text], .fec-contact input[type=email], .fec-contact input[type=url], .fec-contact select, .fec-contact textarea {
    width: 100%; box-sizing: border-box; padding: 9px 11px; border: 1px solid #ccc; border-radius: 6px; font: inherit; background: #fff; }
.fec-contact textarea { min-height: 170px; resize: vertical; }
.fec-contact .fc-hint { display: block; color: #777; font-size: .86em; margin-top: 5px; }
.fec-contact .fc-err { display: block; color: #a12b1f; font-size: .88em; margin-top: 5px; }
.fec-contact .fc-alert { border-radius: 8px; padding: 12px 14px; margin: 0 0 18px; font-size: .95em; }
.fec-contact .fc-alert--err { background: #fdecec; border: 1px solid #f3b9b9; color: #8a1f1f; }
.fec-contact .fc-alert--ok { background: #e8f5f1; border: 1px solid #b7e0d3; color: #14523f; }
.fec-contact .fc-hp { position: absolute; left: -9999px; top: -9999px; height: 0; overflow: hidden; }
.fec-contact .fc-send { display: inline-flex; align-items: center; gap: 8px; padding: 12px 26px; border: 0; border-radius: 8px;
    background: #2f6f4f; color: #fff; font-size: 1.02em; font-weight: 600; cursor: pointer; }
.fec-contact .fc-send:hover { background: #275e43; }
.fec-contact .fc-foot { color: #777; font-size: .86em; margin: 14px 0 18px; }
.fec-contact .fc-side h2 { font-size: 1.05em; margin: 0 0 10px; }
.fec-contact .fc-side ul { list-style: none; margin: 0; padding: 0; }
.fec-contact .fc-side li { border-top: 1px solid #ececec; padding: 10px 0; font-size: .92em; }
.fec-contact .fc-side li strong { display: block; }
.fec-contact .fc-side a { color: #2f6f4f; }
.fec-contact .fc-ts { margin: 4px 0 6px; }
@media (max-width: 820px) {
    .fec-contact .fc-grid { grid-template-columns: 1fr; }
    .fec-contact .fc-row--two { grid-template-columns: 1fr; }
}
</style>
@endpush

@section('content')
@php
    $topicNow = old('topic', request('topic', ''));
    $mailboxes = [];
    foreach ($topics as $key => $t) {
        $mailboxes[$t['to']][] = $t['label'];
    }
@endphp
<div class="fec-contact">
    <h1>Contact us</h1>
    <p class="fc-lede">Tell us what your message is about and it goes straight to the people who handle it. Spotted something wrong in a review or a bonus? Corrections come first: include the page and, if you can, where the right information is.</p>

    <div class="fc-grid">
        <div>
            @if ($sent !== '')
                <div class="fc-alert fc-alert--ok" role="status">
                    <strong>Thank you, your message has been sent.</strong>
                    We answer at the email address you gave us.
                </div>
            @endif
            @if ($errors->has('form'))
                <div class="fc-alert fc-alert--err" role="alert">{{ $errors->first('form') }}</div>
            @elseif ($errors->any())
                <div class="fc-alert fc-alert--err" role="alert">Please check the fields marked below.</div>
            @endif

            <form class="fc-card" method="post" action="/contactus/" novalidate>
                @csrf
                <input type="hidden" name="ts" value="{{ $stamp }}">

                <div class="fc-row">
                    <label for="fc-topic">What is your message about? <span class="fc-req">*</span></label>
                    <select name="topic" id="fc-topic" required>
                        <option value="">Choose a topic</option>
                        @foreach ($topics as $key => $t)
                            <option value="{{ $key }}" @selected($topicNow === $key)>{{ $t['label'] }}</option>
                        @endforeach
                    </select>
                    <span class="fc-hint" id="fc-topic-hint">{{ $topics[$topicNow]['hint'] ?? '' }}</span>
                    @error('topic')<span class="fc-err">{{ $message }}</span>@enderror
                </div>

                <div class="fc-row fc-row--two">
                    <div>
                        <label for="fc-name">Your name <span class="fc-req">*</span></label>
                        <input type="text" name="name" id="fc-name" value="{{ old('name') }}" maxlength="100" autocomplete="name" required>
                        @error('name')<span class="fc-err">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label for="fc-email">Your email <span class="fc-req">*</span></label>
                        <input type="email" name="email" id="fc-email" value="{{ old('email') }}" maxlength="254" autocomplete="email" required>
                        @error('email')<span class="fc-err">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="fc-row" id="fc-url-row">
                    <label for="fc-url">Page this is about <span class="fc-req" id="fc-url-req">*</span></label>
                    <input type="url" name="page_url" id="fc-url" value="{{ old('page_url') }}" maxlength="500" placeholder="https://www.freeextrachips.com/...">
                    @error('page_url')<span class="fc-err">{{ $message }}</span>@enderror
                </div>

                <div class="fc-row">
                    <label for="fc-subject">Subject <span class="fc-req">*</span></label>
                    <input type="text" name="subject" id="fc-subject" value="{{ old('subject') }}" maxlength="150" required>
                    @error('subject')<span class="fc-err">{{ $message }}</span>@enderror
                </div>

                <div class="fc-row">
                    <label for="fc-message">Message <span class="fc-req">*</span></label>
                    <textarea name="message" id="fc-message" maxlength="5000" required>{{ old('message') }}</textarea>
                    @error('message')<span class="fc-err">{{ $message }}</span>@enderror
                </div>

                <div class="fc-hp" aria-hidden="true">
                    <label for="fc-website">Website</label>
                    <input type="text" name="website" id="fc-website" value="" tabindex="-1" autocomplete="off">
                </div>

                @if ($turnstileSiteKey !== '')
                    <div class="fc-ts"><div class="cf-turnstile" data-sitekey="{{ $turnstileSiteKey }}"></div></div>
                @endif

                <button type="submit" class="fc-send">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                    Send message
                </button>
                <p class="fc-foot">We use your name and email only to answer you. See our <a href="/privacy-policy">Privacy Policy</a>.</p>
            </form>
        </div>

        <aside class="fc-side">
            <h2>Prefer email?</h2>
            <ul>
                @foreach ($mailboxes as $address => $labels)
                    <li><strong>{{ implode(' · ', $labels) }}</strong><a href="mailto:{{ $address }}">{{ $address }}</a></li>
                @endforeach
            </ul>
            <p class="fc-foot">Brands and affiliate managers: commercial relationships never decide our ratings. See our <a href="/affiliate-advertiser-disclosure">Affiliate Disclosure</a>.</p>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
@if ($turnstileSiteKey !== '')
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endif
@php
    $topicsJs = [];
    foreach ($topics as $key => $t) {
        $topicsJs[$key] = ['url' => $t['url'], 'hint' => $t['hint']];
    }
@endphp
<script>
(function () {
    var topics = {!! json_encode($topicsJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
    var sel = document.getElementById('fc-topic');
    var hint = document.getElementById('fc-topic-hint');
    var row = document.getElementById('fc-url-row');
    var req = document.getElementById('fc-url-req');
    if (!sel) return;
    function sync() {
        var t = topics[sel.value] || { url: 'none', hint: '' };
        hint.textContent = t.hint;
        row.style.display = t.url === 'none' ? 'none' : '';
        req.style.display = t.url === 'required' ? '' : 'none';
    }
    sel.addEventListener('change', sync);
    sync();
})();
</script>
@endpush
