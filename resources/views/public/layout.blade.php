@php($inst = \Crater\Support\PublicSite::institution())
@php($levels = \Crater\Support\PublicSite::levels())
@php($appLogin = rtrim(config('app.url'), '/').'/login')
<!DOCTYPE html>
<html lang="es-AR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Escuela Nueva Austral · Puerto Madryn')</title>
    <meta name="description" content="@yield('description', 'Escuela Nueva Austral: nivel primario, secundario y terciario en Puerto Madryn, Chubut.')">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="es_AR">
    <meta property="og:site_name" content="Escuela Nueva Austral">
    <meta property="og:title" content="@yield('title', 'Escuela Nueva Austral · Puerto Madryn')">
    <meta property="og:description" content="@yield('description', 'Primario, Secundario y Terciario en Puerto Madryn.')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary">
    <meta name="theme-color" content="#0F2340">
    <link rel="icon" type="image/svg+xml" href="/images/ena-owl.svg">
    <link rel="shortcut icon" href="/assets/img/favicons/favicon-32x32.png">
    <link rel="preload" as="font" type="font/woff2" href="/assets/fonts/ena/barlow-400.woff2" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="/assets/fonts/ena/barlow-600.woff2" crossorigin>
    <link rel="stylesheet" href="/assets/css/sitio.css?v=2">
</head>

<body class="sitio @yield('body_class')">
    <a class="saltar" href="#contenido">Saltar al contenido</a>

    <header class="cabecera" id="cabecera">
        <div class="contenedor cabecera__fila">
            <a class="marca" href="/" aria-label="Escuela Nueva Austral, inicio">
                <img src="/images/ena-owl.svg" alt="" width="30" height="55">
                <span class="marca__texto">
                    <span class="marca__nombre">Escuela Nueva Austral</span>
                    <span class="marca__lugar">Puerto Madryn · Chubut</span>
                </span>
            </a>

            <button class="menu-boton" id="menu-boton" type="button" aria-expanded="false" aria-controls="nav-principal" aria-label="Abrir menú">
                <span></span><span></span><span></span>
            </button>

            <nav class="nav" id="nav-principal" aria-label="Principal">
                <ul>
                    @foreach ($levels as $item)
                        <li><a href="/{{ $item['slug'] }}" @if (($current ?? null) === $item['slug']) aria-current="page" @endif>{{ $item['name'] }}</a></li>
                    @endforeach
                    <li><a href="/#preinscripcion">Preinscripción</a></li>
                    <li><a href="/#contacto">Contacto</a></li>
                </ul>
                <a class="boton boton--borde boton--chico" href="{{ $appLogin }}">Ingresar a la Suite</a>
            </nav>
        </div>
    </header>

    <main id="contenido">
        @yield('content')
    </main>

    <footer class="pie">
        <div class="contenedor pie__grilla">
            <div>
                <img src="/images/ena-logo-institucional.svg" alt="Escuela Nueva Austral" class="pie__logo" width="260" height="60">
                <p class="pie__texto">Primario · Secundario · Terciario<br>Instituto de Nivel Superior Técnico Nueva Austral N.º 1831</p>
            </div>
            <div>
                <p class="pie__titulo">Niveles</p>
                <ul class="pie__lista">
                    @foreach ($levels as $item)
                        <li><a href="/{{ $item['slug'] }}">{{ $item['name'] }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <p class="pie__titulo">Contacto</p>
                <ul class="pie__lista">
                    <li><a href="{{ $inst['map'] }}" target="_blank" rel="noopener">{{ $inst['address'] }}, {{ $inst['city'] }}</a></li>
                    <li><a href="tel:{{ $inst['phone_link'] }}">{{ $inst['phone'] }}</a></li>
                    <li><a href="mailto:{{ $inst['email'] }}">{{ $inst['email'] }}</a></li>
                    <li><a href="https://instagram.com/{{ $inst['instagram'] }}" target="_blank" rel="noopener">Instagram &#64;{{ $inst['instagram'] }}</a></li>
                </ul>
            </div>
        </div>
        <div class="contenedor pie__legal">
            <span>© {{ date('Y') }} Escuela Nueva Austral</span>
            <a href="{{ $appLogin }}">Acceso del personal</a>
        </div>
    </footer>

    <script>
        (function () {
            var boton = document.getElementById('menu-boton')
            var nav = document.getElementById('nav-principal')
            var cabecera = document.getElementById('cabecera')
            if (boton && nav) {
                boton.addEventListener('click', function () {
                    var abierto = nav.classList.toggle('nav--abierta')
                    boton.setAttribute('aria-expanded', abierto ? 'true' : 'false')
                })
                nav.addEventListener('click', function (e) {
                    if (e.target.tagName === 'A') { nav.classList.remove('nav--abierta'); boton.setAttribute('aria-expanded', 'false') }
                })
            }
            var marcar = function () { cabecera.classList.toggle('cabecera--solida', window.scrollY > 24) }
            marcar(); window.addEventListener('scroll', marcar, { passive: true })
            if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                var obs = new IntersectionObserver(function (entradas) {
                    entradas.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('aparece--visible'); obs.unobserve(e.target) } })
                }, { threshold: 0.12 })
                document.querySelectorAll('.aparece').forEach(function (el) { obs.observe(el) })
            } else {
                document.querySelectorAll('.aparece').forEach(function (el) { el.classList.add('aparece--visible') })
            }
        })()
    </script>
</body>

</html>
