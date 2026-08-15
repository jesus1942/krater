/*
 * ena-mano.js — trazos a mano alzada para la landing de Escuela Nueva Austral.
 *
 * Dibuja marcos, subrayados, resaltados y globos con temblor sobre un SVG que
 * se superpone a cada seccion. Adaptado del script de la pieza institucional
 * para funcionar en un layout fluido: redibuja al cambiar el tamano de la
 * ventana y respeta prefers-reduced-motion.
 *
 * Uso: cada contenedor con clase .mano-lienzo recibe un <svg class="tinta">
 * y todos sus descendientes con data-mano dentro son trazados.
 *
 *   data-mano   caja | bloque | subrayado | resaltado | linea | globo
 *   data-color  color del trazo
 *   data-fondo  relleno (solo caja, bloque y globo)
 *   data-grosor ancho del trazo en px
 *   data-sep    separacion del subrayado respecto de la linea de base
 */
(function () {
  'use strict'

  var NS = 'http://www.w3.org/2000/svg'

  function prng(seed) {
    return function () {
      seed |= 0
      seed = (seed + 0x6d2b79f5) | 0
      var t = Math.imul(seed ^ (seed >>> 15), 1 | seed)
      t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t
      return ((t ^ (t >>> 14)) >>> 0) / 4294967296
    }
  }

  function suavizar(pts) {
    var d = 'M' + pts[0][0].toFixed(1) + ' ' + pts[0][1].toFixed(1)
    for (var i = 1; i < pts.length; i++) {
      var p = pts[i - 1]
      var c = pts[i]
      d +=
        ' Q' +
        p[0].toFixed(1) +
        ' ' +
        p[1].toFixed(1) +
        ' ' +
        ((p[0] + c[0]) / 2).toFixed(1) +
        ' ' +
        ((p[1] + c[1]) / 2).toFixed(1)
    }
    return d
  }

  // Perimetro de un rectangulo con las esquinas redondeadas, desviando cada
  // punto un poco hacia afuera o adentro para simular el pulso de la mano.
  function rectAMano(x, y, w, h, j, seed, r) {
    var rnd = prng(seed)
    var pts = []
    var k = Math.max(0, Math.min(r || 10, w / 2, h / 2))

    function ruido() {
      return (rnd() - 0.5) * j * 2
    }

    function lado(x1, y1, x2, y2) {
      var len = Math.hypot(x2 - x1, y2 - y1)
      var n = Math.max(2, Math.round(len / 22))
      for (var i = 0; i <= n; i++) {
        var t = i / n
        pts.push([x1 + (x2 - x1) * t + ruido(), y1 + (y2 - y1) * t + ruido()])
      }
    }

    // Cuarto de circunferencia con centro (cx, cy), de angulo a0 a a1.
    function esquina(cx, cy, a0, a1) {
      if (k < 0.5) return
      var n = Math.max(3, Math.round(k / 5))
      for (var i = 1; i < n; i++) {
        var a = a0 + (a1 - a0) * (i / n)
        pts.push([cx + Math.cos(a) * k + ruido(), cy + Math.sin(a) * k + ruido()])
      }
    }

    var PI = Math.PI
    lado(x + k, y, x + w - k, y)
    esquina(x + w - k, y + k, -PI / 2, 0)
    lado(x + w, y + k, x + w, y + h - k)
    esquina(x + w - k, y + h - k, 0, PI / 2)
    lado(x + w - k, y + h, x + k, y + h)
    esquina(x + k, y + h - k, PI / 2, PI)
    lado(x, y + h - k, x, y + k)
    esquina(x + k, y + k, PI, 1.5 * PI)
    pts.push(pts[0])
    return suavizar(pts) + ' Z'
  }

  function lineaAMano(x1, y1, x2, y2, j, seed) {
    var rnd = prng(seed)
    var n = Math.max(4, Math.round(Math.hypot(x2 - x1, y2 - y1) / 26))
    var pts = []
    for (var i = 0; i <= n; i++) {
      var t = i / n
      pts.push([
        x1 + (x2 - x1) * t + (rnd() - 0.5) * j,
        y1 + (y2 - y1) * t + (rnd() - 0.5) * j * 2.2,
      ])
    }
    return suavizar(pts)
  }

  function trazo(svg, d, attrs) {
    var p = document.createElementNS(NS, 'path')
    p.setAttribute('d', d)
    for (var k in attrs) {
      if (Object.prototype.hasOwnProperty.call(attrs, k)) p.setAttribute(k, attrs[k])
    }
    svg.appendChild(p)
    return p
  }

  function dibujarLienzo(lienzo) {
    var svg = lienzo.querySelector(':scope > svg.tinta')
    if (!svg) {
      svg = document.createElementNS(NS, 'svg')
      svg.setAttribute('class', 'tinta')
      svg.setAttribute('aria-hidden', 'true')
      svg.setAttribute('focusable', 'false')
      lienzo.insertBefore(svg, lienzo.firstChild)
    }
    while (svg.firstChild) svg.removeChild(svg.firstChild)

    var caja = lienzo.getBoundingClientRect()
    if (!caja.width || !caja.height) return
    svg.setAttribute('viewBox', '0 0 ' + caja.width + ' ' + caja.height)

    var seed = 11
    lienzo.querySelectorAll('[data-mano]').forEach(function (el) {
      var r = el.getBoundingClientRect()
      if (!r.width || !r.height) return

      var pad = parseFloat(el.dataset.margen || 0)
      var x = r.left - caja.left - pad
      var y = r.top - caja.top - pad
      var w = r.width + pad * 2
      var h = r.height + pad * 2
      var tipo = el.dataset.mano
      var col = el.dataset.color || '#0F2340'
      var relleno = el.dataset.fondo || 'none'
      var sw = parseFloat(el.dataset.grosor || 2.4)
      seed += 97

      if (tipo === 'caja') {
        if (relleno !== 'none') {
          trazo(svg, rectAMano(x, y, w, h, 1.4, seed, 12), { fill: relleno, stroke: 'none' })
        }
        trazo(svg, rectAMano(x, y, w, h, 1.9, seed + 5, 12), {
          fill: 'none',
          stroke: col,
          'stroke-width': sw,
          'stroke-linecap': 'round',
        })
        trazo(svg, rectAMano(x + 0.8, y + 0.8, w - 1.6, h - 1.6, 2.4, seed + 41, 12), {
          fill: 'none',
          stroke: col,
          'stroke-width': sw * 0.55,
          'stroke-linecap': 'round',
          opacity: 0.5,
        })
      } else if (tipo === 'bloque') {
        trazo(svg, rectAMano(x, y, w, h, 2.2, seed, 14), { fill: relleno, stroke: 'none' })
        trazo(svg, rectAMano(x, y, w, h, 2.6, seed + 9, 14), {
          fill: 'none',
          stroke: col,
          'stroke-width': sw,
          'stroke-linecap': 'round',
        })
      } else if (tipo === 'subrayado') {
        var sep = parseFloat(el.dataset.sep || 6)
        trazo(svg, lineaAMano(x, y + h + sep, x + w, y + h + sep, 2.6, seed), {
          fill: 'none',
          stroke: col,
          'stroke-width': sw,
          'stroke-linecap': 'round',
        })
        trazo(svg, lineaAMano(x + 4, y + h + sep + 5, x + w - 6, y + h + sep + 5, 3.2, seed + 17), {
          fill: 'none',
          stroke: col,
          'stroke-width': sw * 0.6,
          'stroke-linecap': 'round',
          opacity: 0.55,
        })
      } else if (tipo === 'resaltado') {
        // Un trazo de marcador por cada linea de texto, para que el resaltado
        // acompane el corte de linea en vez de tapar el bloque entero.
        var lineas = el.getClientRects()
        for (var li = 0; li < lineas.length; li++) {
          var lr = lineas[li]
          if (lr.width < 4 || lr.height < 4) continue
          var lx = lr.left - caja.left
          var ly = lr.top - caja.top
          var grosor = lr.height * 0.8
          var margenCap = grosor / 2
          trazo(
            svg,
            lineaAMano(
              lx + margenCap * 0.55,
              ly + lr.height * 0.6,
              lx + lr.width - margenCap * 0.55,
              ly + lr.height * 0.6,
              2.4,
              seed + li * 13
            ),
            {
              fill: 'none',
              stroke: col,
              'stroke-width': grosor,
              'stroke-linecap': 'round',
              opacity: 0.9,
            }
          )
        }
      } else if (tipo === 'linea') {
        trazo(svg, lineaAMano(x, y + h / 2, x + w, y + h / 2, 2.4, seed), {
          fill: 'none',
          stroke: col,
          'stroke-width': sw,
          'stroke-linecap': 'round',
        })
      } else if (tipo === 'globo') {
        trazo(svg, rectAMano(x, y, w, h, 2.0, seed, Math.min(w, h) / 2), {
          fill: relleno,
          stroke: col,
          'stroke-width': sw,
          'stroke-linecap': 'round',
        })
      }
    })
  }

  function dibujarTodo() {
    document.querySelectorAll('.mano-lienzo').forEach(dibujarLienzo)
    document.documentElement.dataset.mano = 'listo'
  }

  window.dibujarAMano = dibujarTodo

  function arrancar() {
    dibujarTodo()

    var pendiente = null
    var anchoPrevio = window.innerWidth

    function reprogramar() {
      clearTimeout(pendiente)
      pendiente = setTimeout(dibujarTodo, 180)
    }

    window.addEventListener('resize', function () {
      // En mobile la barra del navegador cambia el alto sin cambiar el layout:
      // solo redibujamos si el ancho se movio, o si tambien cambio el alto en desktop.
      if (window.innerWidth !== anchoPrevio || window.innerWidth > 900) {
        anchoPrevio = window.innerWidth
        reprogramar()
      }
    })

    // Acordeones, imagenes tardias y cualquier cambio de alto de las secciones.
    if (typeof ResizeObserver === 'function') {
      var ro = new ResizeObserver(reprogramar)
      document.querySelectorAll('.mano-lienzo').forEach(function (el) {
        ro.observe(el)
      })
    }
  }

  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(function () {
      setTimeout(arrancar, 60)
    })
  } else {
    window.addEventListener('load', arrancar)
  }
})()
