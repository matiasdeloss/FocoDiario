<?php

/*
 * Datos de la vista Estudio: límites de los tiempos editables y fichas de métodos.
 * Solo se enlazan fuentes que se abrieron o aparecieron en una búsqueda. Donde no hay
 * cifras verificadas, no se citan. Niveles de evidencia: alta, moderada, poca.
 */

$dunlosky = ['Dunlosky et al. (2013), revisión de técnicas de estudio', 'https://pubmed.ncbi.nlm.nih.gov/26173288/'];
$cepeda = ['Cepeda et al. (2006), práctica distribuida (317 experimentos)', 'https://digitalcommons.usf.edu/psy_facpub/1771/'];

return [

    // [mínimo, máximo] de cada tiempo editable (minutos; ciclos en cantidad de pomodoros).
    'limites' => [
        'foco' => [5, 180],
        'descanso' => [1, 60],
        'largo' => [5, 90],
        'ciclos' => [2, 12],
    ],

    'nota_estilos' => 'La teoría de los "estilos de aprendizaje" (visual, auditivo, kinestésico) sostiene que cada persona aprende mejor si se le enseña en su modo preferido. La revisión de Pashler, McDaniel, Rohrer y Bjork (2008) concluyó que no hay evidencia adecuada que la respalde. Por eso esta página no clasifica a nadie: propone métodos y deja que compares los resultados con tus propios registros.',
    'nota_estilos_fuente' => ['Pashler et al. (2008), Learning styles: concepts and evidence', 'https://pubmed.ncbi.nlm.nih.gov/26162104/'],

    'metodos' => [

        [
            'clave' => 'pomodoro',
            'nombre' => 'Pomodoro',
            'preset' => 'clasico',
            'que_es' => 'Alternar bloques de foco de tiempo fijo con descansos cortos, y un descanso largo cada varios bloques. La versión clásica usa 25 minutos de foco y 5 de descanso, con una pausa larga (15 a 30 minutos) cada 4 bloques. Lo creó Francesco Cirillo.',
            'pasos' => [
                'Elegí una sola tarea y escribí qué vas a avanzar.',
                'Poné el temporizador y trabajá sin abrir nada que no sea la tarea.',
                'Cuando suene, descansá de verdad: pararte, tomar agua, mirar lejos, sin celular.',
                'Después de cuatro bloques, hacé el descanso largo.',
            ],
            'cuando' => 'Cuando cuesta arrancar, hay tareas largas para partir en trozos o tendés a distraerte.',
            'error' => 'Interrumpir el bloque para revisar mensajes, o usar el descanso para el celular y volver con más dispersión.',
            'evidencia' => [
                'nivel' => 'poca',
                'texto' => 'Hay estudios chicos con estudiantes y resultados mixtos. En una comparación de descansos fijos contra descansos elegidos por cada persona (Biwer et al. 2023), los fijos se asociaron con mejor ánimo y con eficiencia parecida en menos tiempo, y otro estudio reciente encontró que la fatiga y la motivación evolucionaban distinto sin diferencias finales claras. Es una forma de organizar el tiempo, no una técnica de aprendizaje probada por sí misma.',
                'fuentes' => [
                    ['Biwer et al. (2023), descansos Pomodoro vs. descansos libres', 'https://pubmed.ncbi.nlm.nih.gov/36859717/'],
                    ['Estudio reciente sobre Pomodoro, Flowtime y descansos libres', 'https://pmc.ncbi.nlm.nih.gov/articles/PMC12292963'],
                ],
            ],
        ],

        [
            'clave' => 'bloques-largos',
            'nombre' => 'Bloques largos de foco',
            'preset' => 'bloques_largos',
            'que_es' => 'Sesiones de 50 a 90 minutos de trabajo concentrado en una sola cosa, con un descanso más largo al final (por ejemplo 50 minutos de foco y 10 de descanso).',
            'pasos' => [
                'Elegí una tarea exigente que necesite continuidad (escribir, resolver problemas, leer un capítulo).',
                'Dejá todo lo demás fuera de la vista y definí antes qué querés tener listo al terminar.',
                'Trabajá el bloque entero; si aparece una idea ajena, anotala y seguí.',
                'Descansá 10 minutos o más antes de otro bloque, y no encadenes más de tres o cuatro por día.',
            ],
            'cuando' => 'Para tareas profundas donde cortar cada 25 minutos rompe el hilo.',
            'error' => 'Estirar el bloque sin descanso porque "estoy en racha" y llegar agotado al segundo.',
            'evidencia' => [
                'nivel' => 'poca',
                'texto' => 'Se apoya en Ericsson, Krampe y Tesch-Römer (1993): violinistas de élite sostenían unas 3,5 a 4 horas diarias de práctica deliberada, en sesiones de no más de 60 a 90 minutos. Es un estudio con músicos de élite y la teoría de la práctica deliberada tiene críticas, así que no es una ley para cualquier tarea.',
                'fuentes' => [
                    ['Ericsson et al. (1993), notas sobre el artículo', 'https://notes.andymatuschak.org/zEkCRJXM9NYCXxzFoDaNhL'],
                    ['Revisión crítica de la práctica deliberada', 'https://pmc.ncbi.nlm.nih.gov/articles/PMC7461852/'],
                ],
            ],
        ],

        [
            'clave' => 'recuperacion-activa',
            'nombre' => 'Práctica de recuperación activa',
            'preset' => null,
            'que_es' => 'Estudiar intentando recordar en lugar de releer: cerrás los apuntes y respondés preguntas, escribís lo que recordás o hacés tarjetas y te autoevaluás.',
            'pasos' => [
                'Leé o mirá el material una vez.',
                'Cerrá todo y escribí o decí todo lo que recuerdes, o respondé preguntas sobre el tema.',
                'Abrí los apuntes y comparalos con lo que produjiste. Marcá lo que faltó o estaba mal.',
                'Volvé a intentarlo más tarde solo con lo que falló.',
            ],
            'cuando' => 'Antes de un examen y al terminar cada tema, cuando ya viste el contenido una primera vez.',
            'error' => 'Mirar la respuesta enseguida, sin intentar recordar primero, o creer que "me suena" equivale a saberlo.',
            'evidencia' => [
                'nivel' => 'alta',
                'texto' => 'Dunlosky et al. (2013) le dieron utilidad alta a la práctica de exámenes (practice testing). En el experimento de Roediger y Karpicke (2006), a los cinco minutos releer rindió más, pero a los dos días y a la semana quienes se habían evaluado recordaron bastante más que quienes releyeron. Se siente más difícil y por eso parece peor, pero rinde más a largo plazo.',
                'fuentes' => [
                    $dunlosky,
                    ['Roediger y Karpicke (2006), Test-enhanced learning', 'https://journals.sagepub.com/doi/10.1111/j.1467-9280.2006.01693.x'],
                ],
            ],
        ],

        [
            'clave' => 'repaso-espaciado',
            'nombre' => 'Repaso espaciado',
            'preset' => null,
            'que_es' => 'Repartir los repasos en el tiempo, con intervalos que van creciendo, en vez de concentrarlos en una sola tanda. Un esquema simple es repasar a 1, 3, 7 y 14 días.',
            'pasos' => [
                'Al terminar un tema, anotá la fecha y creá recordatorios de repaso a 1, 3, 7 y 14 días.',
                'En cada repaso, empezá recordando sin mirar (recuperación activa) y después verificá.',
                'Si algo falla, volvé a acortar el intervalo para esa parte.',
            ],
            'cuando' => 'Cuando querés retener algo por semanas o meses, y para materias con examen lejano.',
            'error' => 'Dejar todo para la noche anterior (cramming): sirve para el examen inmediato, pero se olvida rápido.',
            'evidencia' => [
                'nivel' => 'alta',
                'texto' => 'Dunlosky et al. (2013) le dieron utilidad alta a la práctica distribuida. Cepeda et al. (2006) reunieron 317 experimentos y encontraron que distribuir el estudio favorece la retención frente a concentrarlo. Los intervalos exactos (1, 3, 7, 14) son una regla práctica, no un valor demostrado.',
                'fuentes' => [$dunlosky, $cepeda],
            ],
        ],

        [
            'clave' => 'feynman',
            'nombre' => 'Técnica Feynman',
            'preset' => null,
            'que_es' => 'Explicar un concepto con palabras simples, como si se lo contaras a alguien que no sabe nada del tema, para descubrir dónde no lo entendés realmente.',
            'pasos' => [
                'Escribí el nombre del concepto arriba de una hoja.',
                'Explicalo con tus palabras y ejemplos simples, sin copiar la definición.',
                'Marcá los puntos donde te trabaste o usaste jerga que no podrías explicar.',
                'Volvé al material solo para esos puntos y reescribí la explicación.',
            ],
            'cuando' => 'Con conceptos que creés entender pero no podés explicar, o antes de rendir un oral.',
            'error' => 'Repetir de memoria el texto del libro y creer que eso es explicar.',
            'evidencia' => [
                'nivel' => 'moderada',
                'texto' => 'No hay estudios que evalúen la técnica con ese nombre. Se apoya en dos prácticas que Dunlosky et al. (2013) calificaron con utilidad moderada: la interrogación elaborativa (preguntarse por qué algo es cierto) y la autoexplicación. Por eso el nivel es una inferencia a partir de esas prácticas.',
                'fuentes' => [$dunlosky],
            ],
        ],

        [
            'clave' => 'cornell',
            'nombre' => 'Notas Cornell',
            'preset' => null,
            'que_es' => 'Un formato de apuntes con tres zonas: una columna de notas a la derecha, una columna de pistas o preguntas a la izquierda y un resumen abajo. Lo desarrolló la Universidad de Cornell.',
            'pasos' => [
                'Durante la clase o la lectura, anotá las ideas principales en la columna grande.',
                'Al terminar, escribí en la columna izquierda preguntas o palabras clave por cada bloque de notas.',
                'Redactá abajo un resumen de dos o tres frases con tus palabras.',
                'Para repasar, tapá las notas y respondé las preguntas de la columna izquierda.',
            ],
            'cuando' => 'Para clases y textos donde tomás apuntes propios y después los vas a repasar.',
            'error' => 'Llenar solo la columna de notas y no volver a las preguntas, con lo que queda un apunte más sin recuperación.',
            'evidencia' => [
                'nivel' => 'poca',
                'texto' => 'Se recomienda mucho, pero la evidencia empírica sobre su efecto en la retención es inconclusa: hay estudios chicos con resultados positivos y otros con pruebas débiles. Lo que probablemente aporta es el paso de preguntas y repaso tapando las notas, que es recuperación activa.',
                'fuentes' => [
                    ['Guía del Learning Strategies Center de Cornell', 'https://lsc.cornell.edu/how-to-study/taking-notes/cornell-note-taking-system/'],
                ],
            ],
        ],

        [
            'clave' => 'mapas-conceptuales',
            'nombre' => 'Mapas conceptuales',
            'preset' => null,
            'que_es' => 'Un diagrama de nodos y flechas donde cada nodo es un concepto y cada flecha lleva una frase que dice cómo se relacionan.',
            'pasos' => [
                'Listá entre 10 y 20 conceptos clave del tema.',
                'Ubicá el más general arriba y ordená los demás por niveles.',
                'Unilos con flechas y escribí en cada una el verbo o la frase que expresa la relación.',
                'Buscá relaciones entre ramas distintas y revisá que cada frase tenga sentido leída en voz alta.',
            ],
            'cuando' => 'Para temas con muchas partes relacionadas (procesos, sistemas, teorías) y para ver la estructura antes de un examen.',
            'error' => 'Copiar el índice del libro en forma de cajas, sin frases de relación: queda una lista dibujada.',
            'evidencia' => [
                'nivel' => 'moderada',
                'texto' => 'El metaanálisis de Nesbit y Adesope (2006) reunió 55 estudios con 5.818 participantes y asoció el uso de mapas conceptuales con mayor retención de conocimiento, con efectos que van de pequeños a grandes según cómo se usan y con qué se comparan. Dunlosky et al. (2013) no lo evaluaron como técnica separada.',
                'fuentes' => [
                    ['Nesbit y Adesope (2006), metaanálisis de mapas conceptuales', 'https://journals.sagepub.com/doi/10.3102/00346543076003413'],
                ],
            ],
        ],

        [
            'clave' => 'sq3r',
            'nombre' => 'Método SQ3R',
            'preset' => null,
            'que_es' => 'Un método para leer textos: Survey (explorar), Question (preguntar), Read (leer), Recite (recitar) y Review (repasar). Lo propuso Francis Robinson.',
            'pasos' => [
                'Explorar: mirá títulos, gráficos, la introducción y el resumen para ver la estructura.',
                'Preguntar: convertí cada título en una pregunta que quieras responder.',
                'Leer: leé una sección buscando responder esa pregunta.',
                'Recitar: cerrá el texto y respondé la pregunta con tus palabras.',
                'Repasar: al final, recorré las preguntas y volvé a responderlas, en otro momento del día o de la semana.',
            ],
            'cuando' => 'Con capítulos y textos académicos largos que no conviene leer de principio a fin como una novela.',
            'error' => 'Hacer solo las primeras tres letras (explorar, preguntar y leer) y saltarse recitar y repasar, que son las que más rinden.',
            'evidencia' => [
                'nivel' => 'poca',
                'texto' => 'Como paquete completo la evidencia es escasa y hay revisiones que concluyen que no rinde más que otras estrategias. Sus partes de recitar y repasar equivalen a práctica de recuperación, que sí tiene respaldo alto, y las preguntas se parecen a la interrogación elaborativa (utilidad moderada).',
                'fuentes' => [
                    ['The Learning Scientists sobre SQ3R', 'https://www.learningscientists.org/blog/2021/3/4-1'],
                    $dunlosky,
                ],
            ],
        ],

        [
            'clave' => 'entrelazado',
            'nombre' => 'Entrelazado (interleaving)',
            'preset' => null,
            'que_es' => 'Mezclar tipos de problemas o temas dentro de una misma sesión, en lugar de practicar todos los de un tipo seguidos y recién después pasar al siguiente.',
            'pasos' => [
                'Armá una lista de ejercicios de dos o tres tipos distintos del mismo curso.',
                'Alterná el orden: uno de cada tipo, o al azar, en vez de diez seguidos del mismo.',
                'Antes de resolver cada uno, preguntate qué tipo es y qué método corresponde.',
                'Revisá qué tipos confundís y dedicales más práctica.',
            ],
            'cuando' => 'En matemática, física, programación y otras materias donde hay que reconocer qué método usar. Mejor cuando ya viste cada tipo por separado.',
            'error' => 'Entrelazar temas que recién estás aprendiendo, sin haberlos entendido por separado, o mezclar cosas muy distintas entre sí.',
            'evidencia' => [
                'nivel' => 'moderada',
                'texto' => 'El metaanálisis de Brunmair y Richter (2019), con 59 estudios, encontró un efecto moderado a favor del entrelazado (g = 0,42). Depende del material: fue mayor con imágenes como pinturas, pequeño con tareas de matemática y con textos y palabras el resultado fue ambiguo o favoreció el estudio por bloques.',
                'fuentes' => [
                    ['Brunmair y Richter (2019), Similarity matters', 'https://www.semanticscholar.org/paper/Similarity-matters:-A-meta-analysis-of-interleaved-Brunmair-Richter/bb5392e8eaf53a38cc0d147f301cce74cecb4436'],
                ],
            ],
        ],

        [
            'clave' => 'planificacion-dia',
            'nombre' => 'Planificación del día',
            'preset' => null,
            'que_es' => 'Decidir antes de empezar qué vas a hacer y en qué momento: elegir una o tres tareas clave y asignarles bloques de tiempo concretos, con márgenes y descansos.',
            'pasos' => [
                'La noche anterior o al empezar, elegí una a tres tareas importantes.',
                'Asignales un bloque con hora de inicio y de fin, y poné las más exigentes en tu mejor momento de energía.',
                'Dejá huecos para imprevistos, comidas y descanso.',
                'Al final del día, mirá qué se cumplió y ajustá el plan de mañana.',
            ],
            'cuando' => 'Todos los días de estudio, sobre todo si tenés varias materias o fechas de entrega.',
            'error' => 'Llenar el día sin márgenes: el primer imprevisto rompe todo el plan y se abandona.',
            'evidencia' => [
                'nivel' => 'moderada',
                'texto' => 'Un metaanálisis sobre gestión del tiempo (Aeon et al. 2021) halló asociaciones moderadas con mejor rendimiento académico y bienestar. Son correlaciones: no prueban que este método concreto cause la mejora.',
                'fuentes' => [
                    ['Aeon et al. (2021), metaanálisis de gestión del tiempo', 'https://journals.plos.org/plosone/article?id=10.1371%2Fjournal.pone.0245066'],
                ],
            ],
        ],

    ],
];
