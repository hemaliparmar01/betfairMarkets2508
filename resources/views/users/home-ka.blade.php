<div class="container">
    
    <!-- 1. Hero Section (ქართული) -->
    <div class="hero-section">
        <div class="badge"><i class="fas fa-bolt"></i> ბაზრის უპირატესობა</div>
        <h1>დაიჭირე ბაზარზე უპირატესობა <br>Pinnacle-ის ვარდნებით</h1>
        <p class="subtitle">Pinnacle-ის კოეფიციენტები ყოველთვის უსწრებს ბაზარს, რაც გაძლევს რეალურ დროში უპირატესობას Betfair-თან შედარებით. შენიშნე ტენდენცია, განახორციელე შენი სტრატეგია და დააფიქსირე მოგება მატჩის დაწყებამდე.</p>
    </div>

    <!-- 2. ვიზუალური დემო ბარათი (ქართული) -->
    <div class="demo-card">
        <div class="demo-header">
            <span class="demo-title">ცოცხალი მაგალითი: პრემიერ ლიგის მატჩი</span>
            <span class="edge-badge"><i class="fas fa-arrow-up"></i> +9% უპირატესობა</span>
        </div>
        
        <!-- Pinnacle Row -->
        <div class="demo-row pinnacle">
            <div class="bookmaker-name"><i class="fas fa-chart-line" style="color: var(--landing-danger);"></i> Pinnacle</div>
            <div class="odds-change">
                <span class="odds-old">2.50</span>
                <i class="fas fa-arrow-right" style="font-size: 12px; color: var(--landing-text-muted);"></i>
                <span class="odds-new">2.20</span>
            </div>
            <div class="row-note" style="color: var(--landing-danger);">ჭკვიანი ფული შემოდის (ვარდნა)</div>
        </div>

        <!-- Betfair Row -->
        <div class="demo-row betfair">
            <div class="bookmaker-name"><i class="fas fa-exchange-alt" style="color: var(--landing-success);"></i> Betfair</div>
            <div class="odds-change">
                <span class="odds-current">2.40</span>
            </div>
            <div class="row-note" style="color: var(--landing-success);">ფასი ჯერ კიდევ ხელმისაწვდომია (ჩამორჩენა)</div>
        </div>
    </div>

    <!-- 3. როგორ მუშაობს (3 ნაბიჯი - ქართული) -->
    <div class="steps-grid">
        <div class="step-card">
            <div class="step-icon"><i class="fas fa-building"></i></div>
            <div class="step-title">1. ჭკვიანი ფული შემოდის</div>
            <div class="step-desc">Pinnacle აღმოაჩენს მკვეთრ ქმედებებს და ამცირებს კოეფიციენტს ყველა სხვა ბუკმეიკერზე ადრე.</div>
        </div>
        <div class="step-card">
            <div class="step-icon"><i class="fas fa-satellite-dish"></i></div>
            <div class="step-title">2. ჩვენ ვაფიქსირებთ</div>
            <div class="step-desc">ჩვენი WebSocket ტექნოლოგია აფიქსირებს ვარდნის ზუსტ მილიწამს.</div>
        </div>
        <div class="step-card">
            <div class="step-icon"><i class="fas fa-hand-holding-usd"></i></div>
            <div class="step-title">3. შენ იღებ მოგებას</div>
            <div class="step-desc">Betfair-ს ჯერ არ შეუცვლია ფასი. შენ დებ ფსონს ძველ, მაღალ კოეფიციენტზე და ინახავ უპირატესობას.</div>
        </div>
    </div>

    <!-- 4. CTA ღილაკი (ქართული) - განახლებული ლინკით -->
    <a href="{{ route('football.pinnacle-odds') }}" class="cta-btn">ნახე Pinnacle-ის ცოცხალი ვარდნები <i class="fas fa-arrow-right" style="margin-left: 8px;"></i></a>

</div>
