<?php

namespace Crater\Support;

use Crater\Services\Site\SiteContent;

/**
 * Contenido del sitio publico: datos institucionales y una ficha por nivel.
 * Lo de aca son los textos predeterminados; lo cargado en Configuracion >
 * Sitio publico (SiteContent) los reemplaza. Las vistas solo presentan.
 */
class PublicSite
{
    public static function institution(): array
    {
        $saved = app(SiteContent::class)->all();
        $images = app(SiteContent::class)->images();
        $base = static::institutionDefaults();
        foreach (SiteContent::INSTITUTION_FIELDS as $field) {
            if (isset($saved['institution.'.$field])) {
                $base[$field] = $saved['institution.'.$field];
            }
        }
        $base['phone_link'] = '+54'.ltrim(preg_replace('/\D/', '', $base['phone']), '0');
        $base['whatsapp'] = preg_replace('/\D/', '', $base['whatsapp']);
        $base['instagram'] = ltrim($base['instagram'], '@');
        $base['map'] = 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($base['address'].', '.$base['city'].', Argentina');
        $base['hero_image'] = $images['hero']['url'] ?? (file_exists(public_path('images/landing/institucion.jpg')) ? '/images/landing/institucion.jpg' : null);

        return $base;
    }

    public static function institutionDefaults(): array
    {
        return [
            'name' => 'Escuela Nueva Austral',
            'tagline' => 'Una misma escuela, de la primaria al título técnico superior.',
            'about' => 'En Escuela Nueva Austral acompañamos a cada estudiante desde los primeros años de la escolaridad hasta su formación técnica de nivel superior, con equipos docentes propios en cada nivel y un mismo proyecto institucional.',
            'address' => 'De los Avellanos 4094',
            'city' => 'Puerto Madryn, Chubut',
            'phone' => '0280 503 3597',
            'whatsapp' => '5492805033597',
            'email' => 'ena.terciario@gmail.com',
            'instagram' => 'ena.terciario',
        ];
    }

    /** Niveles con lo guardado aplicado; el contacto vacio usa el de la institucion. */
    public static function levels(): array
    {
        $saved = app(SiteContent::class)->all();
        $images = app(SiteContent::class)->images();
        $inst = static::institution();
        $levels = static::levelDefaults();
        foreach ($levels as $slug => &$level) {
            $get = fn ($field) => $saved['level.'.$slug.'.'.$field] ?? null;
            $level['tagline'] = $get('tagline') ?? $level['tagline'];
            $level['summary'] = $get('summary') ?? $level['summary'];
            foreach ([1, 2, 3] as $i) {
                $level['highlights'][$i - 1] = [
                    'title' => $get('highlight_'.$i.'_title') ?? $level['highlights'][$i - 1]['title'],
                    'text' => $get('highlight_'.$i.'_text') ?? $level['highlights'][$i - 1]['text'],
                ];
            }
            $phone = $get('phone') ?? $inst['phone'];
            $level['contact'] = ['phone' => $phone, 'phone_link' => '+54'.ltrim(preg_replace('/\D/', '', $phone), '0'),
                'whatsapp' => preg_replace('/\D/', '', $get('whatsapp') ?? $inst['whatsapp']), 'email' => $get('email') ?? $inst['email']];
            $level['image'] = $images['level.'.$slug]['url'] ?? null;
            $level['meeting'] = $get('meeting_date') ? ['date' => $get('meeting_date'), 'link' => $get('meeting_link')] : null;
        }

        return $levels;
    }

    public static function levelDefaults(): array
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
                'experience_title' => 'Aprender, descubrir y compartir',
                'experience_intro' => 'Una propuesta que combina los aprendizajes fundamentales con el acompañamiento cotidiano y distintas formas de expresión.',
                'experiences' => [
                    ['eyebrow' => 'Aprendizajes fundamentales', 'title' => 'Leer, escribir y pensar', 'text' => 'Construir bases sólidas en lectura, escritura y pensamiento matemático para seguir aprendiendo.'],
                    ['eyebrow' => 'Acompañamiento', 'title' => 'Cada trayectoria importa', 'text' => 'Seguimiento pedagógico cercano y diálogo permanente con las familias.'],
                    ['eyebrow' => 'Formación integral', 'title' => 'Expresarse de distintas maneras', 'text' => 'Arte, música, deporte y convivencia como parte de la experiencia escolar.'],
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
                'experience_title' => 'Una etapa para construir proyectos',
                'experience_intro' => 'El nivel secundario fortalece la formación general y abre oportunidades para explorar intereses, profundizar conocimientos y proyectar el futuro.',
                'experiences' => [
                    ['eyebrow' => 'Formación general', 'title' => 'Conocimientos para comprender', 'text' => 'Una base amplia para desarrollar pensamiento crítico y abordar distintas áreas del conocimiento.'],
                    ['eyebrow' => 'Aprender haciendo', 'title' => 'Proyectos en contexto', 'text' => 'Proyectos interdisciplinarios y salidas educativas para relacionar lo trabajado en clase con situaciones concretas.'],
                    ['eyebrow' => 'Lo que viene', 'title' => 'Prepararse para el próximo paso', 'text' => 'Orientación hacia los estudios superiores y el mundo del trabajo, con articulación con la formación técnica de la institución.'],
                ],
                'program' => null,
            ],
            'terciario' => [
                'slug' => 'terciario', 'name' => 'Terciario', 'order' => 'Nivel 3', 'accent' => '#a5121c',
                'tagline' => 'Tecnicatura Superior en Tecnologías Aplicadas: formación técnica para el sector productivo de la Patagonia.',
                'summary' => 'La TSTA responde a una necesidad concreta del sector productivo de la Patagonia: formar profesionales capaces de identificar y dar respuesta a las demandas del sector socioproductivo de la región mediante soluciones de implementación tecnológica.',
                'highlights' => [
                    ['title' => '3 años', 'text' => 'Presencial con mediación tecnológica. Inicio del primer año: marzo de 2027.'],
                    ['title' => 'Lunes a viernes, de 18 a 22.30 h', 'text' => 'Horario vespertino, pensado también para quienes trabajan.'],
                    ['title' => 'Título habilitante', 'text' => 'Técnico/a Superior en Tecnologías Aplicadas.'],
                ],
                'experience_title' => 'Tecnología para resolver problemas reales',
                'experience_intro' => 'La Tecnicatura Superior en Tecnologías Aplicadas integra electrónica, programación, comunicaciones y automatización con una mirada práctica.',
                'experiences' => [
                    ['eyebrow' => 'Tecnologías', 'title' => 'Entender cómo funcionan los sistemas', 'text' => 'Electrotecnia, electrónica, redes, comunicaciones, programación y sistemas de control.'],
                    ['eyebrow' => 'Integración', 'title' => 'Conectar hardware y software', 'text' => 'Robótica, sensores, PLC y automatización para comprender y operar sistemas tecnológicos.'],
                    ['eyebrow' => 'Experiencia profesional', 'title' => 'Llevar los conocimientos a la práctica', 'text' => 'Prácticas profesionalizantes y un Proyecto Final Integrador vinculados con entornos reales.'],
                ],
                'program' => [
                    'name' => 'Tecnicatura Superior en Tecnologías Aplicadas',
                    'institute' => 'Instituto de Nivel Superior Técnico Nueva Austral N.º 1831',
                    'outcomes' => [
                        'Programar, operar, integrar y mantener sistemas tecnológicos aplicados.',
                        'Diagnosticar fallas.',
                        'Interpretar documentación técnica.',
                        'Comunicar con eficacia información técnica.',
                        'Participar en la automatización de procesos.',
                        'Analizar y mejorar los sistemas productivos.',
                        'Aplicar normas de seguridad, higiene, calidad y ética profesional.',
                        'Comprender el proceso productivo en contexto real y aportar a él.',
                        'Continuar estudios de nivel superior universitario.',
                    ],
                    'audience' => [
                        ['Egresados del secundario', 'Jóvenes que terminaron el nivel secundario y quieren insertarse rápido en el sector productivo tecnológico.'],
                        ['Adultos que vuelven a estudiar', 'Personas que desean reinsertarse en el sistema educativo con conocimientos tecnológicos.'],
                        ['Trabajadores', 'Quienes necesitan profesionalizarse en el área técnica para mejorar su desempeño laboral.'],
                        ['Inserción laboral regional', 'Jóvenes y adultos interesados en trabajar en el sector productivo de la región.'],
                    ],
                    'modality' => [
                        ['Modalidad', 'Presencial con mediación tecnológica'],
                        ['Días y horarios', 'Lunes a viernes, de 18 a 22.30 h (mayormente)'],
                        ['Título habilitante', 'Técnico/a Superior en Tecnologías Aplicadas'],
                        ['Duración', '3 (tres) años'],
                        ['Inicio de cursada', 'Primer año: marzo de 2027'],
                    ],
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
