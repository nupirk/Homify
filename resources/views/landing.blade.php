@extends('layouts.site')

@section('title', 'Homify — Home services in Nepal, booked in minutes')
@section('description', 'Book verified cleaners, plumbers, electricians and more across Nepal. Track your booking live and pay after the service with cash, eSewa or Khalti.')

@section('head')
    <link rel="stylesheet" href="/css/landing.css">
    <script defer src="https://unpkg.com/@phosphor-icons/web@2"></script>
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "SoftwareApplication",
        "name": "Homify",
        "operatingSystem": "Android",
        "applicationCategory": "HouseApplication",
        "url": "{{ config('app.public_url') }}/",
        "description": "Book verified cleaners, plumbers and electricians across Nepal. Track your booking live and pay after the service with cash, eSewa or Khalti.",
        "screenshot": "{{ config('app.public_url') }}/img/og.png",
        "author": {
            "@@type": "Organization",
            "name": "Homify Services Pvt. Ltd.",
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
                    "text": "No. Pay after the service is done — cash at the door, or online with eSewa or Khalti."
                }
            },
            {
                "@@type": "Question",
                "name": "Are the professionals verified?",
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
                "name": "Which cities does Homify cover?",
                "acceptedAnswer": {
                    "@@type": "Answer",
                    "text": "Homify is rolling out across Nepal. Enter your address in the app to check availability in your area."
                }
            }
        ]
    }
    </script>
@endsection

@section('content')
<div class="lp">

    {{-- ============ HERO ============ --}}
    <section class="lp-hero">
        <div class="container lp-hero__grid">
            <div class="lp-hero__copy">
                <span class="lp-eyebrow hero-anim">
                    <span class="lp-dot" aria-hidden="true"></span>
                    Now serving Kathmandu, Lalitpur &amp; Bhaktapur
                </span>

                <h1 class="lp-h1 hero-anim d1">
                    Your home,<br>
                    <span class="lp-grad">handled.</span>
                </h1>

                <p class="lp-lede hero-anim d2">
                    Verified cleaners, plumbers, electricians and more — booked in minutes,
                    tracked live, and paid for only when the job&rsquo;s done.
                </p>

                <div class="lp-ctas hero-anim d3">
                    <a class="btn btn-primary lp-btn-lg" href="#download">
                        Get the app
                        <i class="ph-bold ph-arrow-right" aria-hidden="true"></i>
                    </a>
                    <a class="btn btn-ghost lp-btn-lg" href="#services">Browse services</a>
                </div>

                <ul class="lp-trust hero-anim d3">
                    <li><i class="ph-bold ph-seal-check" aria-hidden="true"></i> KYC-verified pros</li>
                    <li><i class="ph-bold ph-wallet" aria-hidden="true"></i> Pay after service</li>
                    <li><i class="ph-bold ph-receipt" aria-hidden="true"></i> GST shown upfront</li>
                </ul>
            </div>

            {{-- Layered app preview --}}
            <div class="lp-visual hero-anim d3" aria-hidden="true">
                <span class="lp-glow"></span>

                <div class="lp-phone">
                    <span class="lp-phone__notch"></span>
                    <div class="lp-phone__bar">
                        <span>Deep house cleaning</span>
                        <span class="lp-phone__price">Rs. 5,085</span>
                    </div>

                    <span class="lp-chip"><i class="ph-bold ph-navigation-arrow"></i> Partner on the way</span>

                    <div class="lp-pro">
                        <span class="lp-avatar">RS</span>
                        <div>
                            <strong>Rajesh Shrestha</strong>
                            <small><i class="ph-fill ph-star"></i> 4.9 · 212 jobs</small>
                        </div>
                        <span class="lp-pro__badge"><i class="ph-bold ph-seal-check"></i></span>
                    </div>

                    <ul class="lp-mini-steps">
                        <li class="done"><span class="lp-mini-steps__dot"><i class="ph-bold ph-check"></i></span> Booking confirmed</li>
                        <li class="done"><span class="lp-mini-steps__dot"><i class="ph-bold ph-check"></i></span> Partner assigned</li>
                        <li class="active"><span class="lp-mini-steps__dot"></span> Arriving in 10 min</li>
                        <li><span class="lp-mini-steps__dot"></span> Job completed</li>
                    </ul>

                    <div class="lp-phone__foot">
                        <span>Payment</span>
                        <strong>Pay after service</strong>
                    </div>
                </div>

                <div class="lp-badges">
                    <div class="lp-badge-card">
                        <span class="lp-stars"><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i></span>
                        <div>
                            <strong>4.9 out of 5</strong>
                            <small>2,300+ homes</small>
                        </div>
                    </div>

                    <div class="lp-badge-card">
                        <span class="lp-badge-card__icon"><i class="ph-bold ph-shield-check"></i></span>
                        <div>
                            <strong>No prepayment</strong>
                            <small>Pay at the door</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ STATS ============ --}}
    <section class="lp-stats-wrap">
        <div class="container">
            <ul class="lp-stats reveal">
                <li><strong>12,000+</strong><span>bookings completed</span></li>
                <li><strong>4.9<span class="lp-stat-den">/5</span></strong><span>average customer rating</span></li>
                <li><strong>500+</strong><span>verified professionals</span></li>
                <li><strong>45 min</strong><span>median arrival time</span></li>
            </ul>
        </div>
    </section>

    {{-- ============ SERVICES ============ --}}
    <section class="section lp-section" id="services">
        <div class="container">
            <div class="lp-head reveal">
                <span class="lp-kicker">What you can book</span>
                <h2>Everything your home needs, in one place.</h2>
                <p>Pick a service, choose a time, and let a verified professional handle the rest. The full price — 13% GST included — is shown before you confirm.</p>
            </div>

            <div class="lp-services">
                <a class="lp-card reveal" href="#download" style="--d: 0ms">
                    <span class="lp-card__icon"><i class="ph ph-broom"></i></span>
                    <h3>Home cleaning</h3>
                    <p>Deep cleans, kitchens, bathrooms, sofa and carpet care.</p>
                    <span class="lp-card__go">Book cleaning <i class="ph-bold ph-arrow-right"></i></span>
                </a>

                <a class="lp-card reveal" href="#download" style="--d: 60ms">
                    <span class="lp-card__icon"><i class="ph ph-wrench"></i></span>
                    <h3>Plumbing</h3>
                    <p>Leaks, taps, fittings, blocked drains and bathroom repairs.</p>
                    <span class="lp-card__go">Book plumbing <i class="ph-bold ph-arrow-right"></i></span>
                </a>

                <a class="lp-card reveal" href="#download" style="--d: 120ms">
                    <span class="lp-card__icon"><i class="ph ph-lightning"></i></span>
                    <h3>Electrical</h3>
                    <p>Wiring, fans, lights, switches and appliance fixes.</p>
                    <span class="lp-card__go">Book electrical <i class="ph-bold ph-arrow-right"></i></span>
                </a>

                <a class="lp-card reveal" href="#download" style="--d: 0ms">
                    <span class="lp-card__icon"><i class="ph ph-plant"></i></span>
                    <h3>Gardening</h3>
                    <p>Lawns, hedges, pruning and everyday yard care.</p>
                    <span class="lp-card__go">Book gardening <i class="ph-bold ph-arrow-right"></i></span>
                </a>

                <a class="lp-card reveal" href="#download" style="--d: 60ms">
                    <span class="lp-card__icon"><i class="ph ph-sparkle"></i></span>
                    <h3>Beauty &amp; wellness</h3>
                    <p>Haircuts, styling, manicure and pedicure at home.</p>
                    <span class="lp-card__go">Book beauty <i class="ph-bold ph-arrow-right"></i></span>
                </a>

                <a class="lp-card reveal" href="#download" style="--d: 120ms">
                    <span class="lp-card__icon"><i class="ph ph-laptop"></i></span>
                    <h3>Tech support</h3>
                    <p>Computer repair and smart home setup by appointment.</p>
                    <span class="lp-card__go">Book tech help <i class="ph-bold ph-arrow-right"></i></span>
                </a>
            </div>
        </div>
    </section>

    {{-- ============ HOW IT WORKS ============ --}}
    <section class="section lp-section lp-section--tight" id="how">
        <div class="container">
            <div class="lp-head reveal">
                <span class="lp-kicker">How it works</span>
                <h2>From &ldquo;I need this fixed&rdquo; to done.</h2>
                <p>No phone calls, no haggling, no waiting around for a callback.</p>
            </div>

            <ol class="lp-how">
                <li class="lp-step reveal" style="--d: 0ms">
                    <span class="lp-step__num">1</span>
                    <h3>Pick a service</h3>
                    <p>Browse categories or search for exactly what you need.</p>
                </li>
                <li class="lp-step reveal" style="--d: 80ms">
                    <span class="lp-step__num">2</span>
                    <h3>Choose a time</h3>
                    <p>Pick a slot that suits you and share your address.</p>
                </li>
                <li class="lp-step reveal" style="--d: 160ms">
                    <span class="lp-step__num">3</span>
                    <h3>Track &amp; pay</h3>
                    <p>Follow the job live, then pay cash or online when it&rsquo;s done.</p>
                </li>
            </ol>
        </div>
    </section>

    {{-- ============ WHY HOMIFY ============ --}}
    <section class="section lp-section lp-section--tight" id="why">
        <div class="container lp-why">
            <div class="reveal">
                <div class="lp-head lp-head--left">
                    <span class="lp-kicker">Why Homify</span>
                    <h2>Trust, built into every booking.</h2>
                </div>

                <ul class="lp-why__list">
                    <li>
                        <span class="lp-why__icon"><i class="ph-bold ph-seal-check"></i></span>
                        <div>
                            <strong>Verified professionals</strong>
                            <p>Every partner completes KYC checks and is reviewed by real customers.</p>
                        </div>
                    </li>
                    <li>
                        <span class="lp-why__icon"><i class="ph-bold ph-currency-circle-dollar"></i></span>
                        <div>
                            <strong>Pay after the service</strong>
                            <p>No prepaid risk — hand over cash at the door or pay online afterwards.</p>
                        </div>
                    </li>
                    <li>
                        <span class="lp-why__icon"><i class="ph-bold ph-tag"></i></span>
                        <div>
                            <strong>The price you see is the price</strong>
                            <p>Labour, platform fee and 13% GST, itemised before you confirm.</p>
                        </div>
                    </li>
                    <li>
                        <span class="lp-why__icon"><i class="ph-bold ph-map-pin"></i></span>
                        <div>
                            <strong>Live status, and a chat</strong>
                            <p>Know when your pro is on the way — and message them any time.</p>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="reveal" style="--d: 100ms">
                <div class="lp-receipt">
                    <div class="lp-receipt__head">
                        <span class="lp-receipt__tag">Booking summary</span>
                        <h3>Deep house cleaning</h3>
                        <small>3 BHK · Saturday, 10:00 AM</small>
                    </div>

                    <div class="lp-receipt__rows">
                        <div class="lp-receipt__row"><span>Service</span><strong>Rs. 4,500</strong></div>
                        <div class="lp-receipt__row"><span>Platform fee</span><strong>Rs. 25</strong></div>
                        <div class="lp-receipt__row"><span>GST (13%)</span><strong>Rs. 585</strong></div>
                    </div>

                    <div class="lp-receipt__total">
                        <span>Total</span>
                        <strong>Rs. 5,110</strong>
                    </div>

                    <div class="lp-receipt__foot">
                        <i class="ph-bold ph-info"></i>
                        Payable after the job is completed.
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ TESTIMONIALS ============ --}}
    <section class="section lp-section lp-section--tight">
        <div class="container">
            <div class="lp-head reveal">
                <span class="lp-kicker">Loved in the valley</span>
                <h2>Real homes, real reviews.</h2>
            </div>

            <div class="lp-quotes">
                <figure class="lp-quote reveal" style="--d: 0ms">
                    <span class="lp-stars"><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i></span>
                    <blockquote>Booked a deep clean on Friday night and the team was at my door by ten the next morning. The price on the app was exactly what I paid.</blockquote>
                    <figcaption class="lp-person">
                        <span class="lp-avatar lp-avatar--alt">AK</span>
                        <div><strong>Anisha K.</strong><small>Lalitpur · Deep cleaning</small></div>
                    </figcaption>
                </figure>

                <figure class="lp-quote reveal" style="--d: 80ms">
                    <span class="lp-stars"><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i></span>
                    <blockquote>Our kitchen tap had been leaking for weeks. A plumber came the same afternoon, fixed it, and I paid after he was done. That&rsquo;s how it should work.</blockquote>
                    <figcaption class="lp-person">
                        <span class="lp-avatar lp-avatar--alt">ST</span>
                        <div><strong>Suman T.</strong><small>Kathmandu · Plumbing</small></div>
                    </figcaption>
                </figure>

                <figure class="lp-quote reveal" style="--d: 160ms">
                    <span class="lp-stars"><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i></span>
                    <blockquote>The live status is what won me over. I could see exactly when the electrician was on the way instead of guessing all afternoon.</blockquote>
                    <figcaption class="lp-person">
                        <span class="lp-avatar lp-avatar--alt">PG</span>
                        <div><strong>Priya G.</strong><small>Bhaktapur · Electrical</small></div>
                    </figcaption>
                </figure>
            </div>
        </div>
    </section>

    {{-- ============ FIRST BOOKING OFFER ============ --}}
    <section class="section lp-section lp-section--tight">
        <div class="container">
            <div class="lp-promo reveal">
                <div>
                    <span class="lp-kicker lp-kicker--light">New here?</span>
                    <h2>25% off your first booking.</h2>
                    <p>Apply the code at checkout in the app — 25% off, up to Rs. 500, on your first order.</p>
                </div>
                <div class="lp-promo__code" aria-label="Promo code WELCOME25">
                    WELCOME25
                    <small>first booking only</small>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ PARTNERS ============ --}}
    <section class="section lp-section lp-section--tight" id="partners">
        <div class="container">
            <div class="lp-partner reveal">
                <div class="lp-partner__copy">
                    <span class="lp-kicker lp-kicker--light">For professionals</span>
                    <h2>Earn on your schedule.</h2>
                    <p>Accept jobs that fit your route, keep track of every rupee, and withdraw to your bank or mobile wallet. Applications, documents and payouts live in one partner app.</p>

                    <ul class="lp-partner__list">
                        <li><i class="ph-bold ph-check"></i> Weekly payouts, no chasing</li>
                        <li><i class="ph-bold ph-check"></i> Jobs near your route</li>
                        <li><i class="ph-bold ph-check"></i> Free onboarding &amp; training</li>
                    </ul>

                    <a class="btn lp-btn-light" href="mailto:support@homify.com.np?subject=Partner%20application">
                        Apply as a partner
                        <i class="ph-bold ph-arrow-right" aria-hidden="true"></i>
                    </a>
                </div>

                <div class="lp-partner__panel" aria-hidden="true">
                    <div class="lp-earn">
                        <span class="lp-earn__label">This week</span>
                        <strong class="lp-earn__amount">Rs. 18,640</strong>
                        <span class="lp-earn__trend"><i class="ph-bold ph-trend-up"></i> 12% vs last week</span>

                        <div class="lp-bars">
                            <span style="--h: 42%"></span>
                            <span style="--h: 61%"></span>
                            <span style="--h: 38%"></span>
                            <span style="--h: 78%"></span>
                            <span style="--h: 56%"></span>
                            <span style="--h: 92%"></span>
                            <span style="--h: 70%"></span>
                        </div>

                        <div class="lp-earn__foot">
                            <span>7 jobs completed</span>
                            <span class="lp-earn__pill">Payout on Friday</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ FAQ ============ --}}
    <section class="section lp-section lp-section--tight" id="faq">
        <div class="container">
            <div class="lp-head reveal">
                <span class="lp-kicker">Questions</span>
                <h2>Good to know, before you book.</h2>
            </div>

            <div class="lp-faq">
                <details class="reveal" style="--d: 0ms">
                    <summary>Do I have to pay in advance?</summary>
                    <p>No. Pay after the service is done — cash at the door, or online with eSewa or Khalti.</p>
                </details>
                <details class="reveal" style="--d: 40ms">
                    <summary>Are the professionals verified?</summary>
                    <p>Yes. Every partner is KYC-verified, and you can read honest reviews from other customers before you book.</p>
                </details>
                <details class="reveal" style="--d: 80ms">
                    <summary>Is the price final?</summary>
                    <p>Yes. You see the full price, including 13% GST, before you confirm. No surprises afterwards.</p>
                </details>
                <details class="reveal" style="--d: 120ms">
                    <summary>Can I track my booking?</summary>
                    <p>Yes. You get live status at every step — confirmation, partner assigned, on the way, and job done.</p>
                </details>
                <details class="reveal" style="--d: 160ms">
                    <summary>Which cities does Homify cover?</summary>
                    <p>Homify is rolling out across Nepal. Enter your address in the app to check availability in your area.</p>
                </details>
            </div>
        </div>
    </section>

    {{-- ============ DOWNLOAD ============ --}}
    <section class="section lp-section" id="download" style="padding-top: 0">
        <div class="container">
            <div class="lp-download reveal">
                <div class="lp-download__copy">
                    <span class="lp-kicker">Get started</span>
                    <h2>Book your first service in under a minute.</h2>
                    <p>Download Homify, pick a service and choose a time. Pay only when the job is done.</p>

                    <div class="lp-download__row">
                        <a class="play-badge" href="https://play.google.com/store/apps/details?id=com.nupirk.urban" rel="noopener">
                            <img src="https://cdn.simpleicons.org/googleplay/FFFFFF" alt="" width="26" height="26">
                            <span class="lines"><small>GET IT ON</small><strong>Google Play</strong></span>
                        </a>
                        <span class="lp-note">iOS coming soon</span>
                    </div>
                </div>

                <div class="lp-download__art" aria-hidden="true">
                    <span class="lp-glow lp-glow--soft"></span>
                    <div class="lp-tile lp-tile--1"><i class="ph ph-broom"></i><strong>Cleaning</strong><small>from Rs. 1,500</small></div>
                    <div class="lp-tile lp-tile--2"><i class="ph ph-wrench"></i><strong>Plumbing</strong><small>from Rs. 800</small></div>
                    <div class="lp-tile lp-tile--3"><i class="ph ph-lightning"></i><strong>Electrical</strong><small>from Rs. 900</small></div>
                    <div class="lp-tile lp-tile--4"><i class="ph ph-plant"></i><strong>Gardening</strong><small>from Rs. 700</small></div>
                </div>
            </div>
        </div>
    </section>

</div>
@endsection
