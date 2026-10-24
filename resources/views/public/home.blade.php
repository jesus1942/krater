@extends('public.layout')

@section('title', 'Escuela Nueva Austral · Primario, Secundario y Terciario en Puerto Madryn')
@section('description', 'Escuela Nueva Austral: una trayectoria educativa completa en Puerto Madryn, del nivel primario a la Tecnicatura Superior en Tecnologías Aplicadas.')
@section('body_class', 'sitio--portada')

@section('content')
    @php($inst = \Crater\Support\PublicSite::institution())
    @php($levels = \Crater\Support\PublicSite::levels())

    <section class="portada {{ $inst['hero_image'] ? 'portada--foto' : '' }}"
        @if ($inst['hero_image']) style="--portada-imagen: url('{{ $inst['hero_image'] }}')" @endif
        aria-labelledby="titulo-portada">
        <div class="escena" data-escena="portada" aria-hidden="true"></div>
        <div class="contenedor portada__contenido">
            <p class="volanta volanta--clara">Institución educativa de gestión privada · Puerto Madryn</p>
            <h1 id="titulo-portada" class="portada__titulo">Escuela Nueva Austral</h1>
            <p class="portada__bajada">{{ $inst['tagline'] }}</p>

            <div class="accesos" role="list">
                @foreach ($levels as $item)
                    <a class="acceso" role="listitem" href="/{{ $item['slug'] }}" data-nivel-acceso="{{ $item['slug'] }}" style="--acento: {{ $item['accent'] }}">
                        <span class="acceso__orden">{{ $item['order'] }}</span>
                        <span class="acceso__nombre" style="view-transition-name: nivel-{{ $item['slug'] }}">{{ $item['name'] }}</span>
                        <span class="acceso__texto">{{ $item['tagline'] }}</span>
                        <span class="acceso__ir" aria-hidden="true">Conocer el nivel
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12M11 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="seccion" aria-labelledby="titulo-institucion">
        <div class="contenedor institucion aparece">
            <div>
                <p class="volanta">La institución</p>
                <h2 id="titulo-institucion" class="titulo-seccion">Una trayectoria completa en un mismo lugar</h2>
            </div>
            <div class="institucion__texto">
                <p>{{ $inst['about'] }}</p>
                <dl class="cifras">
                    <div><dt>Niveles</dt><dd>3</dd></div>
                    <div><dt>Carrera técnica superior</dt><dd>1</dd></div>
                    <div><dt>Títulos</dt><dd>Oficiales</dd></div>
                </dl>
            </div>
        </div>
    </section>

    <section class="seccion seccion--gris" aria-labelledby="titulo-niveles">
        <div class="contenedor">
            <p class="volanta">Nuestra propuesta</p>
            <h2 id="titulo-niveles" class="titulo-seccion">Elegí el nivel</h2>
            <div class="niveles">
                @foreach ($levels as $item)
                    <article class="nivel aparece" style="--acento: {{ $item['accent'] }}">
                        <p class="nivel__orden">{{ $item['order'] }}</p>
                        <h3 class="nivel__nombre">{{ $item['name'] }}</h3>
                        <p class="nivel__resumen">{{ $item['summary'] }}</p>
                        <ul class="nivel__lista">
                            @foreach ($item['highlights'] as $h)
                                <li>{{ $h['title'] }}</li>
                            @endforeach
                        </ul>
                        <a class="enlace-flecha" href="/{{ $item['slug'] }}">Ver {{ mb_strtolower($item['name']) }}</a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    @include('public.partials.preinscription')
    @include('public.partials.contact')
@endsection
