@extends('layouts.site')

@section('head')
    <script defer src="https://unpkg.com/@phosphor-icons/web@2"></script>
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "SoftwareApplication",
        "name": "Urban: Home Services Nepal",
        "operatingSystem": "Android",
        "applicationCategory": "HouseApplication",
        "url": "{{ config('app.public_url') }}/",
        "description": "Book verified cleaners, plumbers and electricians across Nepal. Track your booking live and pay after the service with cash, eSewa, Khalti or ConnectIPS.",
        "screenshot": "{{ config('app.public_url') }}/img/og.png",
        "author": {
            "@@type": "Organization",
            "name": "Urban Services Pvt. Ltd.",
            "url": "{{ config('app.public_url') }}/"
        }
    }
    </script>
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "FAQPage",
        "mainEntity": [
            {
                "@@type": "Question",
                "name": "Do I have to pay in advance?",
                "acceptedAnswer": {
                    "@@type": "Answer",
                    "text": "No. Pay after the service is done — cash at the door, or online with eSewa, Khalti or ConnectIPS."
                }
            },
            {
                "@@type": "Question",
                "name": "Are the partners verified?",
                "acceptedAnswer": {
                    "@@type": "Answer",
                    "text": "Yes. Every partner is KYC-verified, and you can read honest reviews from other customers before you book."
                }
            },
            {
                "@@type": "Question",
                "name": "Is the price final?",
                "acceptedAnswer": {
                    "@@type": "Answer",
                    "text": "Yes. You see the full price, including 13% GST, before you confirm. No surprises afterwards."
                }
            },
            {
                "@@type": "Question",
                "name": "Can I track my booking?",
                "acceptedAnswer": {
                    "@@type": "Answer",
                    "text": "Yes. You get live status at every step — confirmation, partner assigned, on the way, and job done."
                }
            },
            {
                "@@type": "Question",
                "name": "Which cities does Urban cover?",
                "acceptedAnswer": {
                    "@@type": "Answer",
                    "text": "Urban is rolling out across Nepal. Enter your address in the app to check availability in your area."
                }
            }
        ]
    }
    </script>
@endsection

@section('content')
    {{-- Hero: split, left copy / right real mini-UI of the app --}}
    <section class="hero">
        <div class="container">
            <div>
                <h1 class="hero-anim">Home services, done right.</h1>
                <p class="lede hero-anim d1">Book verified cleaners, plumbers and electricians in minutes. Track the job, pay only when it&rsquo;s done.</p>
                <div class="hero-ctas hero-anim d2">
                    <a class="btn btn-primary" href="#download">Get the app</a>
                    <a class="btn btn-ghost" href="#how">How it works</a>
                </div>
            </div>

            <div class="hero-anim d3" aria-hidden="true">
                <div class="phone phone-float">
                    <div class="app-top">
                        <span class="app-title">Home Cleaning</span>
                        <span>Rs. 1,525</span>
                    </div>
                    <span class="status-chip"><i class="ph ph-navigation-arrow"></i> Partner on the way</span>
                    <div class="partner-row">
                        <span class="avatar">RS</span>
                        <div>
                            <div class="name">Rajesh S.</div>
                            <div class="meta">Verified partner</div>
                        </div>
                    </div>
                    <ul class="timeline">
                        <li><span class="dot"><i class="ph-bold ph-check"></i></span> Booking confirmed</li>
                        <li><span class="dot"><i class="ph-bold ph-check"></i></span> Partner assigned</li>
                        <li class="pending"><span class="dot"></span> On the way</li>
                        <li class="pending"><span class="dot"></span> Job completed</li>
                    </ul>
                    <div class="pay-row"><span>Payment</span><strong>Pay after service</strong></div>
                </div>
            </div>
        </div>
    </section>

    {{-- Trust band --}}
    <div class="trust-band">
        <div class="container">
            <span><i class="ph ph-currency-circle-dollar"></i> Pay after service</span>
            <span><i class="ph ph-wallet"></i> eSewa, Khalti &amp; ConnectIPS</span>
            <span><i class="ph ph-receipt"></i> 13% GST shown upfront</span>
            <span><i class="ph ph-seal-check"></i> KYC-verified partners</span>
        </div>
    </div>

    {{-- Services --}}
    <section class="section" id="services">
        <div class="container">
            <div class="section-head reveal">
                <h2>What you can book</h2>
                <p>Everyday home services with the full price, 13% GST included, shown before you confirm.</p>
            </div>
            <div class="cards">
                <div class="card reveal" style="--d: 0ms">
                    <div class="icon-chip"><i class="ph ph-broom"></i></div>
                    <h3>Cleaning</h3>
                    <p>Deep cleans, sofas, carpets and regular upkeep.</p>
                </div>
                <div class="card tinted reveal" style="--d: 60ms">
                    <div class="icon-chip"><i class="ph ph-wrench"></i></div>
                    <h3>Plumbing</h3>
                    <p>Leaks, taps, fittings and bathroom repairs.</p>
                </div>
                <div class="card reveal" style="--d: 120ms">
                    <div class="icon-chip"><i class="ph ph-lightning"></i></div>
                    <h3>Electrical</h3>
                    <p>Wiring, fans, lights, switches and appliances.</p>
                </div>
                <div class="card reveal" style="--d: 0ms">
                    <div class="icon-chip"><i class="ph ph-hammer"></i></div>
                    <h3>Carpentry</h3>
                    <p>Doors, shelves, beds and custom woodwork.</p>
                </div>
                <div class="card reveal" style="--d: 60ms">
                    <div class="icon-chip"><i class="ph ph-paint-brush"></i></div>
                    <h3>Painting</h3>
                    <p>Interior and exterior walls, finished clean.</p>
                </div>
                <div class="card tinted reveal" style="--d: 120ms">
                    <div class="icon-chip"><i class="ph ph-plant"></i></div>
                    <h3>Gardening</h3>
                    <p>Lawns, hedges, trimming and yard care.</p>
                </div>
                <div class="card more reveal" style="--d: 0ms">
                    <i class="ph ph-dots-three-outline" style="font-size: 1.5rem"></i>
                    And more &mdash; search the app for anything else you need.
                </div>
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section class="section" id="how" style="padding-top: 0">
        <div class="container">
            <div class="section-head reveal">
                <h2>How it works</h2>
                <p>From pick to pay in four steps.</p>
            </div>
            <div class="steps">
                <div class="step reveal" style="--d: 0ms">
                    <span class="num">1</span>
                    <div>
                        <h3>Pick a service</h3>
                        <p>Browse categories or search for exactly what you need.</p>
                    </div>
                </div>
                <div class="step reveal" style="--d: 60ms">
                    <span class="num">2</span>
                    <div>
                        <h3>Choose a time</h3>
                        <p>Select a date and a slot that works for you.</p>
                    </div>
                </div>
                <div class="step reveal" style="--d: 120ms">
                    <span class="num">3</span>
                    <div>
                        <h3>Pick your pro</h3>
                        <p>See ratings and reviews, or let Urban assign the best available partner.</p>
                    </div>
                </div>
                <div class="step reveal" style="--d: 180ms">
                    <span class="num">4</span>
                    <div>
                        <h3>Track &amp; pay</h3>
                        <p>Follow every step, then pay cash or online when the job is done.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Why Urban: split with second mini-UI --}}
    <section class="section why" id="why" style="padding-top: 0">
        <div class="container">
            <div class="reveal">
                <div class="section-head" style="margin-bottom: 0">
                    <h2>Why people pick Urban</h2>
                </div>
                <ul>
                    <li>
                        <i class="ph ph-seal-check"></i>
                        <div><strong>Verified partners</strong><span>Every professional is KYC-verified and reviewed by real customers.</span></div>
                    </li>
                    <li>
                        <i class="ph ph-currency-circle-dollar"></i>
                        <div><strong>Pay after service</strong><span>No prepaid risk &mdash; cash at the door, or pay online afterwards.</span></div>
                    </li>
                    <li>
                        <i class="ph ph-tag"></i>
                        <div><strong>Transparent pricing</strong><span>See the full price with 13% GST before you book.</span></div>
                    </li>
                    <li>
                        <i class="ph ph-map-pin"></i>
                        <div><strong>Live booking status</strong><span>Notified at every step, from confirmation to completion.</span></div>
                    </li>
                </ul>
            </div>
            <div class="reveal" aria-hidden="true">
                <div class="phone phone-float">
                    <div class="app-top">
                        <span class="app-title">Booking chat</span>
                        <span>Live</span>
                    </div>
                    <span class="status-chip"><i class="ph ph-broadcast"></i> Booking in progress</span>
                    <div class="chat-card">
                        <div class="bubble them">I&rsquo;m 10 minutes away.</div>
                        <div class="bubble me">Great, the gate is open.</div>
                        <div class="bubble-time">10:24</div>
                    </div>
                    <ul class="timeline" style="margin-top: 0.875rem">
                        <li><span class="dot"><i class="ph-bold ph-check"></i></span> Confirmed &amp; paid after job</li>
                        <li class="pending"><span class="dot"></span> Rate your partner when done</li>
                    </ul>
                    <div class="pay-row"><span>Support</span><strong>Chat anytime</strong></div>
                </div>
            </div>
        </div>
    </section>

    {{-- Referral band --}}
    <section class="section" style="padding-top: 0">
        <div class="container">
            <div class="referral reveal">
                <div>
                    <h2>New here? 25% off your first booking.</h2>
                    <p>Use this code at checkout in the app &mdash; 25% off, up to Rs. 500, on your first order.</p>
                </div>
                <div class="code-chip" aria-label="Promo code WELCOME25">
                    WELCOME25
                    <small>first booking only</small>
                </div>
            </div>
        </div>
    </section>

    {{-- Partners --}}
    <section class="section" id="partners" style="padding-top: 0">
        <div class="container">
            <div class="partners reveal">
                <div>
                    <h2>Earn on your schedule</h2>
                    <p>Accept jobs that fit your route, track earnings in real time, and withdraw to your bank or mobile wallet. Training, documents and payouts &mdash; all in one partner app.</p>
                </div>
                <a class="btn btn-primary" href="mailto:support@urban.com.np?subject=Partner%20application">Apply as a partner</a>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="section" id="faq" style="padding-top: 0">
        <div class="container">
            <div class="section-head reveal">
                <h2>Questions, answered</h2>
            </div>
            <div class="faq-list">
                <details class="reveal" style="--d: 0ms">
                    <summary>Do I have to pay in advance?</summary>
                    <p>No. Pay after the service is done &mdash; cash at the door, or online with eSewa, Khalti or ConnectIPS.</p>
                </details>
                <details class="reveal" style="--d: 40ms">
                    <summary>Are the partners verified?</summary>
                    <p>Yes. Every partner is KYC-verified, and you can read honest reviews from other customers before you book.</p>
                </details>
                <details class="reveal" style="--d: 80ms">
                    <summary>Is the price final?</summary>
                    <p>Yes. You see the full price, including 13% GST, before you confirm. No surprises afterwards.</p>
                </details>
                <details class="reveal" style="--d: 120ms">
                    <summary>Can I track my booking?</summary>
                    <p>Yes. You get live status at every step &mdash; confirmation, partner assigned, on the way, and job done.</p>
                </details>
                <details class="reveal" style="--d: 160ms">
                    <summary>Which cities does Urban cover?</summary>
                    <p>Urban is rolling out across Nepal. Enter your address in the app to check availability in your area.</p>
                </details>
            </div>
        </div>
    </section>

    {{-- Download --}}
    <section class="section download" id="download" style="padding-top: 0">
        <div class="container">
            <div class="section-head reveal">
                <h2>Get Urban</h2>
                <p>Free to download. Book your first service in under a minute.</p>
            </div>
            <div class="store-row reveal">
                <a class="play-badge" href="https://play.google.com/store/apps/details?id=com.nupirk.urban" rel="noopener">
                    <img src="https://cdn.simpleicons.org/googleplay/FFFFFF" alt="" width="26" height="26">
                    <span class="lines"><small>GET IT ON</small><strong>Google Play</strong></span>
                </a>
            </div>
            <p class="ios-note">iOS app coming soon to the App Store.</p>
        </div>
    </section>
@endsection
