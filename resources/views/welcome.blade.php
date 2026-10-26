<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>سيبل العقارية | Cible Real Estate CRM</title>
    <link rel="icon" type="image/png" href="{{ route('brand.icon') }}">

    <!-- Vite Styles & Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        .hero-pattern {
            background-image: radial-gradient(rgba(196, 163, 105, 0.12) 1px, transparent 1px),
                              url('{{ route('brand.pattern') }}');
            background-size: 32px 32px, 340px 340px;
            background-position: 0 0, center center;
            background-blend-mode: soft-light;
        }
        .gold-gradient-text {
            background: linear-gradient(135deg, #F5EEDC 0%, #C4A369 50%, #EBDDBB 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .gold-glow {
            box-shadow: 0 0 40px -10px rgba(196, 163, 105, 0.35);
        }
        .glass-card {
            background: rgba(24, 63, 60, 0.45);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(196, 163, 105, 0.22);
        }
    </style>
</head>
<body class="min-h-screen bg-[#0E2624] text-slate-100 flex flex-col selection:bg-[#C4A369] selection:text-[#0E2624] overflow-x-hidden">
    <!-- Background Luxury Ambient Glows -->
    <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden">
        <div class="absolute -top-40 -right-40 w-96 h-96 bg-[#C4A369]/15 rounded-full blur-[120px]"></div>
        <div class="absolute top-1/3 -left-40 w-96 h-96 bg-[#183F3C]/40 rounded-full blur-[140px]"></div>
        <div class="absolute -bottom-40 right-1/4 w-[500px] h-[500px] bg-[#C4A369]/10 rounded-full blur-[160px]"></div>
    </div>

    <!-- Navigation Bar -->
    <header class="relative z-10 w-full border-b border-white/10 bg-[#0E2624]/80 backdrop-blur-md sticky top-0 transition-all">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <!-- Brand Lockup -->
            <a href="/" class="flex items-center gap-3.5 group transition-transform duration-200 hover:scale-[1.02]">
                <img
                    src="{{ route('brand.icon') }}"
                    alt="Cible Real Estate"
                    class="h-11 w-11 object-contain drop-shadow-[0_4px_12px_rgba(196,163,105,0.3)] transition-transform group-hover:rotate-3 duration-300"
                />
                <div class="flex flex-col text-right">
                    <span class="font-['beIN'] text-xl font-bold tracking-tight text-white group-hover:text-[#F5EEDC] transition-colors">
                        سيبل العقارية
                    </span>
                    <span class="font-['Jost'] text-[11px] font-semibold uppercase tracking-[0.22em] text-[#C4A369]">
                        Cible Real Estate
                    </span>
                </div>
            </a>

            <!-- Navigation Links / CTA -->
            <div class="flex items-center gap-4">
                <a
                    href="{{ url('/admin') }}"
                    class="relative inline-flex items-center justify-center gap-2.5 px-6 py-2.5 rounded-xl font-['beIN'] text-sm font-semibold tracking-wide text-[#0E2624] bg-gradient-to-r from-[#DFBC7E] via-[#C4A369] to-[#AB884D] hover:from-[#EBDDBB] hover:to-[#C4A369] transition-all duration-300 gold-glow hover:scale-[1.03] active:scale-[0.98] shadow-lg shadow-[#C4A369]/20"
                >
                    <svg class="w-4 h-4 stroke-[2.2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                    </svg>
                    <span>الدخول إلى لوحة التحكم</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Hero Section -->
    <main class="relative z-10 flex-1 flex flex-col justify-center items-center px-4 sm:px-6 lg:px-8 py-16 lg:py-24 hero-pattern">
        <div class="max-w-4xl mx-auto text-center flex flex-col items-center">
            <!-- Brand Badge -->
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-[#183F3C]/80 border border-[#C4A369]/30 text-xs font-['beIN'] text-[#F5EEDC] mb-8 shadow-inner">
                <span class="w-2 h-2 rounded-full bg-[#C4A369] animate-pulse"></span>
                <span>البوابة التشغيلية المعتمدة • منصة العمل الداخلية لسيبل العقارية</span>
            </div>

            <!-- Logo SVG Display -->
            <div class="mb-8 p-6 rounded-3xl glass-card gold-glow max-w-[280px] sm:max-w-[340px] transition-transform duration-500 hover:scale-105">
                <img
                    src="{{ route('brand.logo') }}"
                    alt="Cible Real Estate Emblem"
                    class="w-full h-auto drop-shadow-[0_8px_24px_rgba(0,0,0,0.5)]"
                />
            </div>

            <!-- Hero Headline -->
            <h1 class="font-['beIN'] text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-white mb-6 leading-[1.25]">
                منظومة العمليات وإدارة علاقات العملاء
                <br>
                <span class="gold-gradient-text text-3xl sm:text-4xl lg:text-5xl">Cible Real Estate CRM</span>
            </h1>

            <!-- Subtitle -->
            <p class="font-['beIN'] text-lg sm:text-xl text-slate-300 max-w-2xl mb-10 leading-relaxed font-normal">
                منصة العمل الداخلية لسيبل العقارية لتنسيق طلبات الإيجار وإعادة البيع، متابعة الصفقات، وتأكيد المعاينات وحماية بيانات العملاء بأعلى معايير الخصوصية.
            </p>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row items-center gap-4 w-full sm:w-auto mb-16">
                <a
                    href="{{ url('/admin') }}"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-8 py-4 rounded-xl font-['beIN'] text-base font-bold text-[#0E2624] bg-gradient-to-r from-[#DFBC7E] via-[#C4A369] to-[#AB884D] hover:from-[#EBDDBB] hover:to-[#C4A369] transition-all duration-300 gold-glow hover:scale-[1.03] active:scale-[0.98] shadow-xl shadow-[#C4A369]/25"
                >
                    <svg class="w-5 h-5 stroke-[2.2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                    <span>الدخول إلى النظام</span>
                </a>

                <a
                    href="#departments"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-4 rounded-xl font-['beIN'] text-base font-medium text-white/90 bg-white/5 hover:bg-white/10 border border-white/15 hover:border-[#C4A369]/40 transition-all duration-200 backdrop-blur-sm"
                >
                    <span>استكشاف قطاعات التشغيل</span>
                </a>
            </div>

            <!-- Department Cards Overview -->
            <div id="departments" class="w-full grid grid-cols-1 md:grid-cols-2 gap-6 text-right">
                <!-- Rental Department -->
                <div class="glass-card rounded-2xl p-7 transition-all duration-300 hover:border-[#C4A369]/60 hover:-translate-y-1 group">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl bg-[#C4A369]/15 border border-[#C4A369]/30 flex items-center justify-center text-[#C4A369] group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6 stroke-[1.8]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                            </svg>
                        </div>
                        <span class="text-xs font-['Jost'] font-bold text-[#C4A369] px-3 py-1 rounded-full bg-[#C4A369]/10 border border-[#C4A369]/20">RENTAL</span>
                    </div>
                    <h3 class="font-['beIN'] text-xl font-bold text-white mb-2">قطاع الإيجار</h3>
                    <p class="font-['beIN'] text-sm text-slate-300 leading-relaxed font-light mb-4">
                        إدارة طلبات الإيجار اليومي والشهري والسنوي، مطابقة تفضيلات الأدوار والمصاعد، وتنسيق المعاينات الميدانية للمندوبين والعملاء.
                    </p>
                    <div class="flex items-center gap-2 text-xs text-[#F5EEDC]/80 font-['beIN'] border-t border-white/5 pt-3">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#C4A369]"></span>
                        <span>متابعة مستمرة • فحص الوحدات قبل الزيارة</span>
                    </div>
                </div>

                <!-- Resale Department -->
                <div class="glass-card rounded-2xl p-7 transition-all duration-300 hover:border-[#C4A369]/60 hover:-translate-y-1 group">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl bg-[#C4A369]/15 border border-[#C4A369]/30 flex items-center justify-center text-[#C4A369] group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6 stroke-[1.8]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            </svg>
                        </div>
                        <span class="text-xs font-['Jost'] font-bold text-[#C4A369] px-3 py-1 rounded-full bg-[#C4A369]/10 border border-[#C4A369]/20">RESALE</span>
                    </div>
                    <h3 class="font-['beIN'] text-xl font-bold text-white mb-2">قطاع إعادة البيع</h3>
                    <p class="font-['beIN'] text-sm text-slate-300 leading-relaxed font-light mb-4">
                        تتبع طلبات المشترين والمستثمرين، دراسة المساحات والميزانيات المستهدفة، وتوثيق الصفقات الاستثمارية وما بعد البيع.
                    </p>
                    <div class="flex items-center gap-2 text-xs text-[#F5EEDC]/80 font-['beIN'] border-t border-white/5 pt-3">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#C4A369]"></span>
                        <span>صفقات معتمدة • عزل مالي وتشغيلي كامل</span>
                    </div>
                </div>
            </div>

            <!-- Security & System Pillars -->
            <div class="w-full mt-12 pt-8 border-t border-white/10 grid grid-cols-1 sm:grid-cols-3 gap-6 text-center">
                <div class="flex items-center justify-center gap-2.5 text-xs font-['beIN'] text-slate-300">
                    <svg class="w-4 h-4 text-[#C4A369]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                    </svg>
                    <span>توثيق ثنائي إلزامي MFA للمديرين</span>
                </div>
                <div class="flex items-center justify-center gap-2.5 text-xs font-['beIN'] text-slate-300">
                    <svg class="w-4 h-4 text-[#C4A369]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                    </svg>
                    <span>عزل صارم للبيانات بين الأقسام</span>
                </div>
                <div class="flex items-center justify-center gap-2.5 text-xs font-['beIN'] text-slate-300">
                    <svg class="w-4 h-4 text-[#C4A369]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                    </svg>
                    <span>نسخ احتياطي يومي مشفر خارج الموقع</span>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
