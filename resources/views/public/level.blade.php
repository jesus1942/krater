@extends('public.layout', ['current' => $level['slug']])

@section('title', 'Nivel '.$level['name'].' · Escuela Nueva Austral')
@section('description', $level['summary'])

@section('content')
    @php($levels = \Crater\Support\PublicSite::levels())
    @php($program = $level['program'])

    <section class="portada-nivel" style="--acento: {{ $level['accent'] }}" aria-labelledby="titulo-nivel">
        <div class="contenedor">
            <nav class="selector" aria-label="Niveles">
                @foreach ($levels as $item)
                    <a href="/{{ $item['slug'] }}" class="selector__opcion {{ $item['slug'] === $level['slug'] ? 'selector__opcion--activa' : '' }}"
                        @if ($item['slug'] === $level['slug']) aria-current="page" @endif>{{ $item['name'] }}</a>
                @endforeach
            </nav>
            <p class="volanta volanta--clara">{{ $level['order'] }} · Escuela Nueva Austral</p>
            <h1 id="titulo-nivel" class="portada-nivel__titulo">Nivel {{ $level['name'] }}</h1>
            <p class="portada-nivel__bajada">{{ $level['tagline'] }}</p>
            <div class="portada-nivel__botones">
                <a class="boton boton--claro" href="#preinscripcion">Preinscripción 2027</a>
                <a class="boton boton--borde-claro" href="#contacto">Contacto</a>
            </div>
        </div>
    </section>

    <section class="seccion" aria-labelledby="titulo-propuesta">
        <div class="contenedor institucion aparece">
            <div>
                <p class="volanta" style="color: {{ $level['accent'] }}">La propuesta</p>
                <h2 id="titulo-propuesta" class="titulo-seccion titulo-seccion--bajada">{{ $level['summary'] }}</h2>
            </div>
            <div class="destacados">
                @foreach ($level['highlights'] as $h)
                    <article class="destacado" style="--acento: {{ $level['accent'] }}">
                        <h3>{{ $h['title'] }}</h3>
                        <p>{{ $h['text'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    @if ($program)
        <section class="seccion seccion--oscura" aria-labelledby="titulo-carrera">
            <div class="contenedor carrera">
                <div class="carrera__encabezado aparece">
                    <div>
                        <p class="volanta volanta--clara">{{ $program['enrollment'] }}</p>
                        <h2 id="titulo-carrera" class="titulo-seccion titulo-seccion--claro">{{ $program['name'] }}</h2>
                        <p class="carrera__titulo-obtenido"><span>Título:</span> {{ $program['degree'] }}</p>
                    </div>
                    @if ($program['image'])
                        <img class="carrera__imagen" src="{{ $program['image'] }}" width="1100" height="685" loading="lazy"
                            alt="Equipamiento de automatización industrial: brazo robótico y tablero eléctrico.">
                    @endif
                </div>
                <h3 class="carrera__subtitulo">Qué vas a aprender</h3>
                <ul class="materias">
                    @foreach ($program['subjects'] as [$name, $text])
                        <li class="materia aparece">
                            <h4>{{ $name }}</h4>
                            <p>{{ $text }}</p>
                        </li>
                    @endforeach
                </ul>
                <p class="carrera__nota">{{ $program['note'] }}</p>
            </div>
        </section>
    @endif

    <section class="seccion seccion--gris" aria-labelledby="titulo-otros">
        <div class="contenedor">
            <h2 id="titulo-otros" class="titulo-seccion">Los otros niveles</h2>
            <div class="niveles niveles--dos">
                @foreach ($levels as $item)
                    @continue($item['slug'] === $level['slug'])
                    <a class="nivel nivel--enlace aparece" href="/{{ $item['slug'] }}" style="--acento: {{ $item['accent'] }}">
                        <p class="nivel__orden">{{ $item['order'] }}</p>
                        <h3 class="nivel__nombre">{{ $item['name'] }}</h3>
                        <p class="nivel__resumen">{{ $item['tagline'] }}</p>
                        <span class="enlace-flecha">Ver {{ mb_strtolower($item['name']) }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    @include('public.partials.preinscription')
    @include('public.partials.contact')
@endsection
