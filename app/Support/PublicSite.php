<?php

namespace Crater\Support;

/**
 * Contenido del sitio publico: datos institucionales y una ficha por nivel.
 * Las vistas de resources/views/public solo arman la presentacion.
 */
class PublicSite
{
    public static function institution(): array
    {
        return [
            'name' => 'Escuela Nueva Austral',
            'address' => 'De los Avellanos 4094',
            'city' => 'Puerto Madryn, Chubut',
            'map' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode('De los Avellanos 4094, Puerto Madryn, Chubut, Argentina'),
            'phone' => '0280 503 3597',
            'phone_link' => '+542805033597',
            'whatsapp' => '5492805033597',
            'email' => 'ena.terciario@gmail.com',
            'instagram' => 'ena.terciario',
            // Foto principal opcional: si existe se usa en la portada.
            'hero_image' => file_exists(public_path('images/landing/institucion.jpg')) ? '/images/landing/institucion.jpg' : null,
        ];
    }

    public static function levels(): array
    {
        return [
            'primario' => [
                'slug' => 'primario', 'name' => 'Primario', 'order' => 'Nivel 1', 'accent' => '#1f6f5c',
                'tagline' => 'Los cimientos para aprender toda la vida.',
                'summary' => 'Lectura, escritura, pensamiento matemático y convivencia, con seguimiento cercano de cada chico y cada chica.',
                'highlights' => [
                    ['title' => 'Jornada escolar completa', 'text' => 'Tiempo para aprender, jugar y compartir dentro de la escuela.'],
                    ['title' => 'Acompañamiento personalizado', 'text' => 'Seguimiento pedagógico de cada estudiante y diálogo permanente con las familias.'],
                    ['title' => 'Arte, música y deporte', 'text' => 'Talleres que completan la formación y desarrollan otras formas de expresión.'],
                ],
                'program' => null,
            ],
            'secundario' => [
                'slug' => 'secundario', 'name' => 'Secundario', 'order' => 'Nivel 2', 'accent' => '#2b4c8c',
                'tagline' => 'Un proyecto propio para cada estudiante.',
                'summary' => 'Formación general sólida y orientación hacia los estudios superiores y el mundo del trabajo.',
                'highlights' => [
                    ['title' => 'Ciclo básico y orientado', 'text' => 'Una trayectoria completa con espacios de profundización en los últimos años.'],
                    ['title' => 'Articulación con el terciario', 'text' => 'Continuidad natural hacia la formación técnica superior dentro de la misma institución.'],
                    ['title' => 'Proyectos y salidas', 'text' => 'Aprendizaje en contexto, con proyectos interdisciplinarios y salidas educativas.'],
                ],
                'program' => null,
            ],
            'terciario' => [
                'slug' => 'terciario', 'name' => 'Terciario', 'order' => 'Nivel 3', 'accent' => '#a5121c',
                'tagline' => 'La Patagonia necesita tecnología. Y quienes sepan hacerla funcionar.',
                'summary' => 'Instituto de Nivel Superior Técnico Nueva Austral N.º 1831. Formación técnica superior orientada a la industria y los servicios de la región.',
                'highlights' => [
                    ['title' => '3 años, presencial', 'text' => 'Cursada en Puerto Madryn con prácticas en entornos reales.'],
                    ['title' => 'Título de nivel superior', 'text' => 'Técnico/a Superior en Tecnologías Aplicadas.'],
                    ['title' => 'Salida laboral real', 'text' => 'Prácticas profesionalizantes en empresas de la región.'],
                ],
                'program' => [
                    'name' => 'Tecnicatura Superior en Tecnologías Aplicadas',
                    'degree' => 'Técnico/a Superior en Tecnologías Aplicadas',
                    'image' => '/images/landing/industrial.jpg',
                    'subjects' => [
                        ['Electrotecnia y Electrónica', 'Principios eléctricos y electrónicos aplicados a sistemas y equipos reales.'],
                        ['Programación', 'Software, algoritmos y soluciones para la automatización y el control.'],
                        ['Redes y Comunicaciones', 'Redes, protocolos y sistemas de comunicación industrial y de datos.'],
                        ['Automatización Industrial', 'Integración y operación de sistemas automatizados en procesos productivos.'],
                        ['Robótica y Sensores', 'Robots, sensores e instrumentación para crear soluciones inteligentes.'],
                        ['PLC y Sistemas de Control', 'Programación y configuración de controladores lógicos.'],
                        ['Mantenimiento de Sistemas', 'Diagnóstico y mejora de equipos y sistemas industriales automatizados.'],
                        ['Prácticas Profesionalizantes', 'Entornos reales, pasantía o proyecto en empresa y Proyecto Final Integrador.'],
                    ],
                    'note' => 'Habilitación institucional: Resolución ME N.º 452/26. Título de validez nacional y aprobación curricular en trámite ante el Ministerio de Educación de Chubut.',
                    'enrollment' => 'Preinscripción 2027 abierta',
                ],
            ],
        ];
    }
}
