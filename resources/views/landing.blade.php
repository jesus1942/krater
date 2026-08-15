{{--
  Portada publica de Escuela Nueva Austral.

  Se sirve en la raiz del sitio ("/") y es accesible sin sesion iniciada.
  El boton "Ingresar a la app" de la cabecera lleva a /login, que monta la SPA
  de la Suite de Gestion.

  Los textos institucionales de nivel primario y secundario son provisorios:
  reemplazarlos por los definitivos que pase la escuela. Los datos del nivel
  terciario salen de la pieza institucional de la Tecnicatura.
--}}
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Escuela Nueva Austral · Puerto Madryn, Chubut</title>
    <meta name="description"
        content="Escuela Nueva Austral: nivel primario, secundario y terciario en Puerto Madryn, Chubut. Tecnicatura Superior en Tecnologias Aplicadas con preinscripcion 2027 abierta.">

    <meta property="og:type" content="website">
    <meta property="og:locale" content="es_AR">
    <meta property="og:site_name" content="Escuela Nueva Austral">
    <meta property="og:title" content="Escuela Nueva Austral · Puerto Madryn">
    <meta property="og:description"
        content="Tres niveles educativos en Puerto Madryn. Preinscripcion 2027 abierta para la Tecnicatura Superior en Tecnologias Aplicadas.">
    <meta property="og:image" content="{{ url('/images/landing/edificio.jpg') }}">
    <meta property="og:url" content="{{ url('/') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="theme-color" content="#0F2340">

    <link rel="icon" type="image/svg+xml" href="/images/ena-owl.svg">
    <link rel="shortcut icon" href="/assets/img/favicons/favicon-32x32.png">
    <link rel="preload" as="font" type="font/woff2" href="/assets/fonts/ena/caveat.woff2" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="/assets/fonts/ena/barlow-400.woff2" crossorigin>
    <link rel="stylesheet" href="/assets/css/landing.css">
</head>

<body class="ena-landing">
    <a class="saltar" href="#contenido">Saltar al contenido</a>

    {{-- ------------------------------------------------------------ cabecera --}}
    <header class="cabecera">
        <div class="contenedor fila">
            <a class="marca" href="/" aria-label="Escuela Nueva Austral, inicio">
                <img src="/images/ena-owl.svg" alt="" width="44" height="81">
                <span class="marca-texto">
                    <span class="nombre">
                        <span class="nombre-largo">Escuela </span>Nueva Austral
                    </span>
                    <span class="bajada">Puerto Madryn · Chubut</span>
                </span>
            </a>

            <button class="menu-boton" type="button" id="menu-boton" aria-expanded="false"
                aria-controls="nav-principal" aria-label="Abrir menu de navegacion">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                    stroke-linecap="round" aria-hidden="true">
                    <path d="M4 7h16M4 12h16M4 17h16" />
                </svg>
            </button>

            <nav class="nav-principal" id="nav-principal" aria-label="Navegacion principal">
                <a href="#niveles">Niveles</a>
                <a href="#terciario">Terciario</a>
                <a href="#preinscripcion">Preinscripci&oacute;n</a>
                <a href="#contacto">Contacto</a>
            </nav>

            <a class="btn btn-rojo btn-ingresar" href="/login">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4" />
                    <path d="M10 17l5-5-5-5M15 12H3" />
                </svg>
                <span>Ingresar<span class="texto-largo"> a la app</span></span>
            </a>
        </div>
    </header>

    <main id="contenido">

        {{-- ---------------------------------------------------------------- hero --}}
        <section class="hero mano-lienzo">
            <div class="contenedor grilla">
                <div>
                    <p class="volanta">Instituci&oacute;n educativa de gesti&oacute;n privada</p>
                    <h1>
                        <span>Escuela</span>
                        <span class="rojo sangria">Nueva Austral</span>
                    </h1>
                    <p class="claim">
                        Tres niveles, una misma escuela.<br>
                        Acompa&ntilde;amos a cada estudiante
                        <em data-mano="resaltado" data-color="#F5D65B">de la primaria al t&iacute;tulo
                            t&eacute;cnico.</em>
                    </p>

                    <ul class="chips">
                        <li class="chip" data-mano="globo" data-color="#0F2340" data-grosor="1.9">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M3 21h18M5 21V8l7-4 7 4v13" />
                                <path d="M10 21v-5h4v5" />
                            </svg>
                            <span>Puerto Madryn, Chubut</span>
                        </li>
                        <li class="chip" data-mano="globo" data-color="#0F2340" data-grosor="1.9">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 4L2 9l10 5 10-5-10-5z" />
                                <path d="M6 11.5V17c0 1.5 3 3 6 3s6-1.5 6-3v-5.5" />
                            </svg>
                            <span>Primario · Secundario · Terciario</span>
                        </li>
                        <li class="chip" data-mano="globo" data-color="#0F2340" data-grosor="1.9">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 3l8 4v5c0 5-3.4 8.2-8 9-4.6-.8-8-4-8-9V7z" />
                                <path d="M9 12l2 2 4-4" />
                            </svg>
                            <span>T&iacute;tulos oficiales</span>
                        </li>
                    </ul>

                    <div class="acciones">
                        <a class="btn btn-rojo" href="#preinscripcion">Preinscripci&oacute;n 2027</a>
                        <a class="btn btn-linea" href="#niveles">Conoc&eacute; los niveles</a>
                    </div>
                </div>

                <div class="polaroid">
                    <div class="marco">
                        <img src="/images/landing/edificio.jpg" width="600" height="450"
                            alt="Frente del edificio de Escuela Nueva Austral, con el b&uacute;ho institucional y el cartel Nueva Austral de Ense&ntilde;anza Superior.">
                        <p class="epigrafe">nuestra sede en Madryn</p>
                    </div>
                    <img class="cinta arriba" src="/images/landing/cinta_azul.png" alt="" aria-hidden="true">
                    <img class="cinta abajo" src="/images/landing/cinta_roja.png" alt="" aria-hidden="true">
                </div>
            </div>
        </section>

        {{-- ------------------------------------------------------------- niveles --}}
        <section class="seccion mano-lienzo" id="niveles" aria-labelledby="titulo-niveles">
            <div class="contenedor">
                <div class="seccion-titulo">
                    <p class="volanta">Nuestra propuesta</p>
                    <h2 id="titulo-niveles" data-mano="subrayado" data-color="#A5121C" data-grosor="3.2"
                        data-sep="4">Tres niveles</h2>
                    <p>Una trayectoria educativa completa en un mismo lugar: desde los primeros
                        a&ntilde;os de la escolaridad hasta un t&iacute;tulo t&eacute;cnico de nivel
                        superior.</p>
                </div>

                <div class="niveles">
                    <article class="nivel" data-mano="caja" data-fondo="#FFFDF7" data-color="#0F2340"
                        data-grosor="2.3">
                        <div class="ico">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M4 5.5A2.5 2.5 0 016.5 3H11v17H6.5A2.5 2.5 0 014 17.5z" />
                                <path d="M20 5.5A2.5 2.5 0 0017.5 3H13v17h4.5a2.5 2.5 0 002.5-2.5z" />
                            </svg>
                        </div>
                        <p class="etiqueta">Nivel 1</p>
                        <h3>Primario</h3>
                        <p>Los cimientos: lectura, escritura, pensamiento matem&aacute;tico y
                            convivencia, con seguimiento cercano de cada chico y cada chica.</p>
                        <ul>
                            <li>Jornada escolar completa</li>
                            <li>Acompa&ntilde;amiento pedag&oacute;gico personalizado</li>
                            <li>Talleres de arte, m&uacute;sica y deporte</li>
                        </ul>
                    </article>

                    <article class="nivel" data-mano="caja" data-fondo="#FFFDF7" data-color="#0F2340"
                        data-grosor="2.3">
                        <div class="ico">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 3L2 8l10 5 10-5-10-5z" />
                                <path d="M6 10.5V16c0 1.6 2.7 3 6 3s6-1.4 6-3v-5.5" />
                                <path d="M21 8.5v5.5" />
                            </svg>
                        </div>
                        <p class="etiqueta">Nivel 2</p>
                        <h3>Secundario</h3>
                        <p>La etapa en la que se define un proyecto propio: formaci&oacute;n general
                            s&oacute;lida y orientaci&oacute;n hacia los estudios superiores y el mundo
                            del trabajo.</p>
                        <ul>
                            <li>Ciclo b&aacute;sico y ciclo orientado</li>
                            <li>Articulaci&oacute;n con el nivel terciario</li>
                            <li>Proyectos y salidas educativas</li>
                        </ul>
                    </article>

                    <article class="nivel" data-mano="caja" data-fondo="#FFFDF7" data-color="#A5121C"
                        data-grosor="2.6">
                        <div class="ico">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="3.2" />
                                <path
                                    d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2.1 2.1M16.9 16.9L19 19M19 5l-2.1 2.1M7.1 16.9L5 19" />
                            </svg>
                        </div>
                        <p class="etiqueta">Nivel 3</p>
                        <h3>Terciario</h3>
                        <p>Instituto de Nivel Superior T&eacute;cnico Nueva Austral N.&ordm; 1831.
                            Formaci&oacute;n t&eacute;cnica de nivel superior orientada a la industria y
                            los servicios de la Patagonia.</p>
                        <ul>
                            <li>Tecnicatura Superior en Tecnolog&iacute;as Aplicadas</li>
                            <li>3 a&ntilde;os · presencial en Puerto Madryn</li>
                            <li>Pr&aacute;cticas profesionalizantes en empresas</li>
                        </ul>
                        <a class="apertura" href="#terciario">Ver la carrera en detalle</a>
                    </article>
                </div>
            </div>
        </section>

        {{-- ------------------------------------------------------------ terciario --}}
        <section class="terciario sobre-oscuro" id="terciario" aria-labelledby="titulo-terciario">
            <div class="contenedor">
                <div class="encabezado">
                    <div>
                        <p class="sello">Instituto N.&ordm; 1831 · Tecnicatura Superior en</p>
                        <h2 id="titulo-terciario">
                            <span class="rojo">Tecnolog&iacute;as</span>
                            <span>Aplicadas</span>
                        </h2>
                        <p class="bajada">La Patagonia necesita tecnolog&iacute;a. Y necesita quienes
                            sepan hacerla funcionar.</p>
                        <ul class="datos-carrera">
                            <li>3 a&ntilde;os</li>
                            <li>Presencial en Puerto Madryn</li>
                            <li>T&iacute;tulo de nivel superior</li>
                            <li>Salida laboral real</li>
                        </ul>
                    </div>

                    <div class="polaroid">
                        <div class="marco">
                            <img src="/images/landing/industrial.jpg" width="1100" height="685"
                                alt="Brazo rob&oacute;tico industrial junto a un tablero el&eacute;ctrico, mientras una persona monitorea el proceso en una tablet.">
                            <p class="epigrafe">taller de automatizaci&oacute;n</p>
                        </div>
                        <img class="cinta arriba" src="/images/landing/cinta_azul.png" alt=""
                            aria-hidden="true">
                        <img class="cinta abajo" src="/images/landing/cinta_roja.png" alt=""
                            aria-hidden="true">
                    </div>
                </div>

                <h3 class="oculto-visual">Qu&eacute; vas a aprender</h3>
                <ul class="materias">
                    <li class="materia">
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M13.5 2L5 13.5h5.2L9.5 22 19 10.2h-5.6z" />
                        </svg>
                        <h4>Electrotecnia y Electr&oacute;nica</h4>
                        <p>Principios el&eacute;ctricos y electr&oacute;nicos aplicados a sistemas y
                            equipos reales.</p>
                    </li>
                    <li class="materia">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"
                            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 7l-5 5 5 5M15 7l5 5-5 5" />
                        </svg>
                        <h4>Programaci&oacute;n</h4>
                        <p>Software, algoritmos y soluciones para la automatizaci&oacute;n y el control.
                        </p>
                    </li>
                    <li class="materia">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="4.5" r="2.5" />
                            <circle cx="4.5" cy="19" r="2.5" />
                            <circle cx="19.5" cy="19" r="2.5" />
                            <path d="M12 7v4M12 11H6.5v5.2M12 11h5.5v5.2" />
                        </svg>
                        <h4>Redes y Comunicaciones</h4>
                        <p>Redes, protocolos y sistemas de comunicaci&oacute;n industrial y de datos.</p>
                    </li>
                    <li class="materia">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="3.2" />
                            <path
                                d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2.1 2.1M16.9 16.9L19 19M19 5l-2.1 2.1M7.1 16.9L5 19" />
                        </svg>
                        <h4>Automatizaci&oacute;n Industrial</h4>
                        <p>Integraci&oacute;n y operaci&oacute;n de sistemas automatizados en procesos
                            productivos.</p>
                    </li>
                    <li class="materia">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 21h9" />
                            <path d="M7 21v-6.5l6.5-4.5" />
                            <circle cx="7" cy="14.5" r="1.7" />
                            <path d="M13.5 10L10 4.5" />
                            <circle cx="8.6" cy="3.4" r="2" />
                            <rect x="14" y="7" width="7" height="5" rx="1.3" />
                        </svg>
                        <h4>Rob&oacute;tica y Sensores</h4>
                        <p>Robots, sensores e instrumentaci&oacute;n para crear soluciones inteligentes.
                        </p>
                    </li>
                    <li class="materia">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="6" y="6" width="12" height="12" rx="2" />
                            <rect x="9.6" y="9.6" width="4.8" height="4.8" rx="1" stroke-width="1.6" />
                            <path d="M10 3v3M14 3v3M10 18v3M14 18v3M3 10h3M3 14h3M18 10h3M18 14h3" />
                        </svg>
                        <h4>PLC y Sistemas de Control</h4>
                        <p>Programaci&oacute;n y configuraci&oacute;n de controladores l&oacute;gicos.
                        </p>
                    </li>
                    <li class="materia">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path
                                d="M15.5 3.5a5 5 0 00-6.1 6.6L3.6 15.9a2 2 0 102.8 2.8l5.8-5.8a5 5 0 006.6-6.1l-2.9 2.9-2.6-.7-.7-2.6z" />
                        </svg>
                        <h4>Mantenimiento de Sistemas</h4>
                        <p>Diagn&oacute;stico y mejora de equipos y sistemas industriales automatizados.
                        </p>
                    </li>
                    <li class="materia destacada">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="2.5" y="7.5" width="19" height="12.5" rx="2" />
                            <path d="M9 7.5V5.5a2 2 0 012-2h2a2 2 0 012 2v2M2.5 13h19" />
                        </svg>
                        <h4>Pr&aacute;cticas Profesionalizantes</h4>
                        <p>Entornos reales · Pasant&iacute;a o proyecto en empresa · Proyecto Final
                            Integrador.</p>
                    </li>
                </ul>

                <div class="titulacion">
                    <svg class="birrete" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 4L2 9l10 5 10-5-10-5z" />
                        <path d="M6 11.5V17c0 1.5 3 3 6 3s6-1.5 6-3v-5.5" />
                        <path d="M21 9.5v5" />
                    </svg>
                    <div>
                        <p class="etiqueta">T&iacute;tulo que obtendr&aacute;s</p>
                        <p class="valor">T&eacute;cnico/a Superior en Tecnolog&iacute;as Aplicadas</p>
                    </div>
                    <p class="nota">Habilitaci&oacute;n institucional: Resoluci&oacute;n ME N.&ordm;
                        452/26. T&iacute;tulo de validez nacional y aprobaci&oacute;n curricular en
                        tr&aacute;mite ante el Ministerio de Educaci&oacute;n de Chubut.</p>
                </div>
            </div>
        </section>

        {{-- -------------------------------------------------------- preinscripcion --}}
        <section class="seccion mano-lienzo" id="preinscripcion" aria-labelledby="titulo-preinscripcion">
            <div class="contenedor">
                <div class="preinscripcion sobre-oscuro" data-mano="bloque" data-fondo="#A5121C"
                    data-color="#7C0D14" data-grosor="3">
                    <svg class="calendario" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="5" width="18" height="16" rx="2.5" />
                        <path d="M3 10h18M8 3v4M16 3v4" />
                        <path d="M12 13.5v2.8l2 1.3" />
                    </svg>
                    <div>
                        <h2 id="titulo-preinscripcion">&iexcl;Preinscripci&oacute;n 2027!</h2>
                        <p>Suma&iacute;te a la lista de interesados de la Tecnicatura Superior en
                            Tecnolog&iacute;as Aplicadas.</p>
                    </div>
                    <div class="botones">
                        <a class="btn btn-claro"
                            href="https://wa.me/5492805033597?text=Hola%2C%20quiero%20informaci%C3%B3n%20sobre%20la%20preinscripci%C3%B3n%202027"
                            target="_blank" rel="noopener">
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path
                                    d="M12 2a10 10 0 00-8.6 15l-1.3 4.7 4.8-1.3A10 10 0 1012 2zm5.8 14.2c-.25.7-1.45 1.35-2 1.4-.5.05-1.15.07-1.85-.12-.43-.13-.98-.32-1.69-.62-2.97-1.28-4.9-4.26-5.05-4.46-.15-.2-1.2-1.6-1.2-3.05s.76-2.16 1.03-2.46c.27-.3.59-.37.78-.37h.56c.18 0 .42-.07.66.5.25.6.84 2.05.91 2.2.07.15.12.32.02.52-.1.2-.15.32-.3.5l-.45.52c-.15.15-.3.31-.13.61.17.3.76 1.25 1.63 2.02 1.12.99 2.06 1.3 2.36 1.45.3.15.47.13.65-.08.17-.2.75-.87.95-1.17.2-.3.4-.25.66-.15.27.1 1.7.8 1.99.95.3.15.5.22.57.35.07.13.07.75-.18 1.45z" />
                            </svg>
                            Escribinos por WhatsApp
                        </a>
                        <a class="btn btn-azul" href="mailto:ena.terciario@gmail.com?subject=Preinscripcion%202027">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="2.5" y="5" width="19" height="14" rx="2" />
                                <path d="M3 6.5l9 6.5 9-6.5" />
                            </svg>
                            Escribinos por mail
                        </a>
                    </div>
                </div>
            </div>
        </section>

        {{-- -------------------------------------------------------------- contacto --}}
        <section class="seccion contacto mano-lienzo" id="contacto" aria-labelledby="titulo-contacto">
            <div class="contenedor">
                <div class="seccion-titulo">
                    <h2 id="titulo-contacto" data-mano="subrayado" data-color="#A5121C" data-grosor="3.2"
                        data-sep="4">D&oacute;nde encontrarnos</h2>
                </div>

                <div class="grilla">
                    <ul class="datos">
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 21s7-5.6 7-11a7 7 0 10-14 0c0 5.4 7 11 7 11z" />
                                <circle cx="12" cy="10" r="2.6" />
                            </svg>
                            <span>
                                <span class="clave">Direcci&oacute;n</span>
                                <span class="valor">Av. Avellanos 4068 · Puerto Madryn, Chubut</span>
                            </span>
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path
                                    d="M5 3.5h3.6l1.8 4.5-2.2 1.6a12 12 0 006.2 6.2l1.6-2.2 4.5 1.8V19a2 2 0 01-2.2 2A16.5 16.5 0 013.5 5.7 2 2 0 015 3.5z" />
                            </svg>
                            <span>
                                <span class="clave">Tel&eacute;fono</span>
                                <span class="valor"><a href="tel:+542805033597">0280 503 3597</a></span>
                            </span>
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="2.5" y="5" width="19" height="14" rx="2" />
                                <path d="M3 6.5l9 6.5 9-6.5" />
                            </svg>
                            <span>
                                <span class="clave">Correo</span>
                                <span class="valor"><a
                                        href="mailto:ena.terciario@gmail.com">ena.terciario@gmail.com</a></span>
                            </span>
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                                aria-hidden="true">
                                <rect x="3" y="3" width="18" height="18" rx="5" />
                                <circle cx="12" cy="12" r="4" />
                                <circle cx="17.4" cy="6.6" r="1.1" fill="currentColor" stroke="none" />
                            </svg>
                            <span>
                                <span class="clave">Instagram del terciario</span>
                                <span class="valor"><a href="https://instagram.com/ena.terciario"
                                        target="_blank" rel="noopener">@ena.terciario</a></span>
                            </span>
                        </li>
                    </ul>

                    <div class="tarjeta-app" data-mano="caja" data-fondo="#FFFDF7" data-color="#0F2340"
                        data-grosor="2.3">
                        <h3>Suite de Gesti&oacute;n ENA</h3>
                        <p>El sistema interno de la escuela para administrar estudiantes, facturaci&oacute;n,
                            cobros y gastos de los tres niveles.</p>
                        <a class="btn btn-azul" href="/login">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4" />
                                <path d="M10 17l5-5-5-5M15 12H3" />
                            </svg>
                            Ingresar a la app
                        </a>
                        <p class="aclaracion">Acceso exclusivo para personal autorizado de la
                            instituci&oacute;n.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    {{-- ----------------------------------------------------------------- pie --}}
    <footer class="pie-sitio sobre-oscuro">
        <div class="contenedor fila">
            <img src="/images/ena-owl.svg" alt="" width="42" height="77">
            <p class="legal">
                Escuela Nueva Austral · Instituto de Nivel Superior T&eacute;cnico Nueva Austral
                N.&ordm;&nbsp;1831<br>
                Av. Avellanos 4068 · Puerto Madryn, Chubut, Argentina
            </p>
            <div class="redes">
                <a href="https://instagram.com/ena.terciario" target="_blank" rel="noopener"
                    aria-label="Instagram del nivel terciario">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                        aria-hidden="true">
                        <rect x="3" y="3" width="18" height="18" rx="5" />
                        <circle cx="12" cy="12" r="4" />
                        <circle cx="17.4" cy="6.6" r="1.1" fill="currentColor" stroke="none" />
                    </svg>
                </a>
                <a href="mailto:ena.terciario@gmail.com" aria-label="Escribirnos por correo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="2.5" y="5" width="19" height="14" rx="2" />
                        <path d="M3 6.5l9 6.5 9-6.5" />
                    </svg>
                </a>
            </div>
        </div>
    </footer>

    <script src="/assets/js/ena-mano.js" defer></script>
    <script>
        (function () {
            var boton = document.getElementById('menu-boton')
            var nav = document.getElementById('nav-principal')
            if (!boton || !nav) return

            function cerrar() {
                nav.removeAttribute('data-abierto')
                boton.setAttribute('aria-expanded', 'false')
                boton.setAttribute('aria-label', 'Abrir menu de navegacion')
            }

            boton.addEventListener('click', function () {
                var abierto = nav.getAttribute('data-abierto') === '1'
                if (abierto) {
                    cerrar()
                } else {
                    nav.setAttribute('data-abierto', '1')
                    boton.setAttribute('aria-expanded', 'true')
                    boton.setAttribute('aria-label', 'Cerrar menu de navegacion')
                }
            })

            nav.addEventListener('click', function (e) {
                if (e.target.tagName === 'A') cerrar()
            })

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && nav.getAttribute('data-abierto') === '1') {
                    cerrar()
                    boton.focus()
                }
            })
        })()
    </script>
</body>

</html>
