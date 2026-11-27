<div class="container">
    
    <!-- 1. Hero Section (English) -->
    <div class="hero-section">
        <div class="badge"><i class="fas fa-bolt"></i> Market Edge</div>
        <h1>Catch the market edge with <br>Pinnacle odds drops</h1>
        <p class="subtitle">Pinnacle odds always lead the market, giving you a real-time advantage over Betfair. Spot the trend, execute your strategy, and secure profit before the match even starts.</p>
    </div>

    <!-- 2. Visual Demo Card (English) -->
    <div class="demo-card">
        <div class="demo-header">
            <span class="demo-title">Live Example: Premier League Match</span>
            <span class="edge-badge"><i class="fas fa-arrow-up"></i> +9% Edge</span>
        </div>
        
        <!-- Pinnacle Row -->
        <div class="demo-row pinnacle">
            <div class="bookmaker-name"><i class="fas fa-chart-line" style="color: var(--landing-danger);"></i> Pinnacle</div>
            <div class="odds-change">
                <span class="odds-old">2.50</span>
                <i class="fas fa-arrow-right" style="font-size: 12px; color: var(--landing-text-muted);"></i>
                <span class="odds-new">2.20</span>
            </div>
            <div class="row-note" style="color: var(--landing-danger);">Sharp money in (Drop)</div>
        </div>

        <!-- Betfair Row -->
        <div class="demo-row betfair">
            <div class="bookmaker-name"><i class="fas fa-exchange-alt" style="color: var(--landing-success);"></i> Betfair</div>
            <div class="odds-change">
                <span class="odds-current">2.40</span>
            </div>
            <div class="row-note" style="color: var(--landing-success);">Price still available (Lag)</div>
        </div>
    </div>

    <!-- 3. How it Works (3 Steps - English) -->
    <div class="steps-grid">
        <div class="step-card">
            <div class="step-icon"><i class="fas fa-building"></i></div>
            <div class="step-title">1. Smart Money Enters</div>
            <div class="step-desc">Pinnacle detects sharp action and drops the odds before any other bookmaker.</div>
        </div>
        <div class="step-card">
            <div class="step-icon"><i class="fas fa-satellite-dish"></i></div>
            <div class="step-title">2. We Detect It</div>
            <div class="step-desc">Our WebSocket technology catches the exact millisecond the odds drop.</div>
        </div>
        <div class="step-card">
            <div class="step-icon"><i class="fas fa-hand-holding-usd"></i></div>
            <div class="step-title">3. You Take Profit</div>
            <div class="step-desc">Betfair hasn't adjusted yet. You back the old price and secure your edge.</div>
        </div>
    </div>

    <!-- 4. CTA Button (English) - Updated Link -->
    <a href="{{ route('football.pinnacle-odds') }}" class="cta-btn">View Live Pinnacle Drops <i class="fas fa-arrow-right" style="margin-left: 8px;"></i></a>

</div>
