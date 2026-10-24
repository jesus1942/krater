@extends('public.layout', ['current' => $level['slug']])

@section('title', 'Nivel '.$level['name'].' · Escuela Nueva Austral')
@section('description', $level['summary'])

@section('content')
    @php($levels = \Crater\Support\PublicSite::levels())
    @php($program = $level['program'])

    <section class="portada-nivel {{ $level['image'] ? 'portada-nivel--foto' : '' }}" style="--acento: {{ $level['accent'] }};@if ($level['image']) --portada-imagen: url('{{ $level['image'] }}');@endif" aria-labelledby="titulo-nivel">
        <div class="escena" data-escena="{{ $level['slug'] }}" aria-hidden="true"></div>
        <div class="contenedor">
            <nav class="selector" aria-label="Niveles">
                @foreach ($levels as $item)
                    <a href="/{{ $item['slug'] }}" class="selector__opcion {{ $item['slug'] === $level['slug'] ? 'selector__opcion--activa' : '' }}"
                        @if ($item['slug'] === $level['slug']) aria-current="page" @endif>{{ $item['name'] }}</a>
                @endforeach
            </nav>
            <p class="volanta volanta--clara">{{ $level['order'] }} · {{ $program['institute'] ?? 'Escuela Nueva Austral' }}</p>
            <h1 id="titulo-nivel" class="portada-nivel__titulo" style="view-transition-name: nivel-{{ $level['slug'] }}">Nivel {{ $level['name'] }}</h1>
            <p class="portada-nivel__bajada">{{ $level['tagline'] }}</p>
            <div class="portada-nivel__botones">
                <a class="boton boton--claro" href="#preinscripcion">Preinscripción 2027</a>
                <a class="boton boton--borde-claro" href="#contacto">Contacto</a>
            </div>
        </div>
    </section>

    @if (!empty($program['institute']))
        <div class="contenedor">
            <div class="membrete">
                <img src="/images/ena-logo-institucional.svg" alt="Escuela Nueva Austral" width="230" height="60">
                <p class="membrete__texto"><strong>{{ $program['institute'] }}</strong><span>{{ $program['name'] }}</span></p>
            </div>
        </div>
    @endif

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

    @if (!empty($program['outcomes']))
        <section class="seccion seccion--gris" aria-labelledby="titulo-logros" style="--acento: {{ $level['accent'] }}">
            <div class="contenedor perfil">
                <div class="aparece">
                    <p class="volanta" style="color: {{ $level['accent'] }}">Perfil del egresado</p>
                    <h2 id="titulo-logros" class="titulo-seccion">¿Qué lograrás con la {{ $program['name'] }}?</h2>
                    <ul class="logros">
                        @foreach ($program['outcomes'] as $outcome)
                            <li>{{ $outcome }}</li>
                        @endforeach
                    </ul>
                </div>
                <aside class="ficha aparece" aria-labelledby="titulo-modalidad">
                    <h3 id="titulo-modalidad" class="ficha__titulo">Modalidad de cursada</h3>
                    <dl>
                        @foreach ($program['modality'] as [$label, $value])
                            <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                    <a class="boton boton--primario" href="#preinscripcion">Quiero inscribirme</a>
                </aside>
            </div>
        </section>

        <section class="seccion" aria-labelledby="titulo-destinatarios">
            <div class="contenedor">
                <p class="volanta" style="color: {{ $level['accent'] }}">Dirigida a</p>
                <h2 id="titulo-destinatarios" class="titulo-seccion">¿Para quién es la carrera?</h2>
                <div class="destinatarios">
                    @foreach ($program['audience'] as [$title, $text])
                        <article class="destinatario aparece" style="--acento: {{ $level['accent'] }}">
                            <h3>{{ $title }}</h3>
                            <p>{{ $text }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if (!empty($level['experiences']))
        <section class="seccion seccion--experiencia" aria-labelledby="titulo-experiencia" style="--acento: {{ $level['accent'] }}">
            <div class="contenedor">
                <div class="experiencia__encabezado aparece">
                    <p class="volanta" style="color: {{ $level['accent'] }}">La experiencia ENA</p>
                    <h2 id="titulo-experiencia" class="titulo-seccion">{{ $level['experience_title'] }}</h2>
                    <p class="experiencia__intro">{{ $level['experience_intro'] }}</p>
                </div>
                <div class="experiencias">
                    @foreach ($level['experiences'] as $i => $experience)
                        <article class="experiencia aparece">
                            <span class="experiencia__numero" aria-hidden="true">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                            <p class="experiencia__eyebrow">{{ $experience['eyebrow'] }}</p>
                            <h3>{{ $experience['title'] }}</h3>
                            <p>{{ $experience['text'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

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

    @if ($level['meeting'])
        <section class="seccion seccion--reunion" aria-labelledby="titulo-reunion">
            <div class="contenedor">
                <div class="reunion aparece" style="--acento: {{ $level['accent'] }}">
                    <div>
                        <p class="volanta" style="color: {{ $level['accent'] }}">Reunión informativa por Zoom</p>
                        <h2 id="titulo-reunion" class="titulo-seccion">Si te interesa conocer más, te esperamos</h2>
                        <p class="reunion__fecha">{{ $level['meeting']['date'] }}</p>
                    </div>
                    @if ($level['meeting']['link'])
                        <a class="boton boton--primario" href="{{ $level['meeting']['link'] }}" target="_blank" rel="noopener">Anotarme en la reunión</a>
                    @endif
                </div>
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

    @include('public.partials.preinscription', ['contact' => $level['contact']])
    @include('public.partials.contact', ['contact' => $level['contact']])
@endsection
