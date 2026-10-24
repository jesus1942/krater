// =====================================================================
// Capa 3D del sitio publico de Escuela Nueva Austral
//  - Portada: una figura de lineas por nivel (Primario, Secundario,
//    Terciario). Al pasar por un acceso, la camara se acerca a su figura.
//  - Pagina de nivel: la figura del nivel a un costado de la portada.
//  - Sin WebGL, sin red o con movimiento reducido el sitio queda igual.
// =====================================================================

const REDUCE = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const host = document.querySelector('[data-escena]');
const clamp = (v, a, b) => Math.max(a, Math.min(b, v));

// Tonos claros de los acentos de cada nivel, legibles sobre el azul de la portada.
const TONOS = { primario: 0x5cc7a6, secundario: 0x8fb0f0, terciario: 0xef6f78 };

async function iniciar() {
  if (REDUCE || !host) return;
  let THREE;
  try {
    THREE = await import('https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js');
  } catch (e) { return; }

  let renderer;
  try {
    renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'low-power' });
  } catch (e) { return; }

  const chico = window.innerWidth < 720;
  const caja = () => host.getBoundingClientRect();
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, chico ? 1.5 : 1.75));
  renderer.setClearColor(0x000000, 0);
  host.appendChild(renderer.domElement);

  const scene = new THREE.Scene();
  scene.fog = new THREE.FogExp2(0x0f2340, 0.018);
  const camera = new THREE.PerspectiveCamera(50, 1, 0.1, 300);

  const linea = (c, o) => new THREE.LineBasicMaterial({ color: c, transparent: true, opacity: o, fog: true });
  const bordes = (geo, c, o) => new THREE.LineSegments(new THREE.EdgesGeometry(geo), linea(c, o));
  const punto = (() => {
    const cv = document.createElement('canvas'); cv.width = cv.height = 64;
    const g = cv.getContext('2d'), r = g.createRadialGradient(32, 32, 0, 32, 32, 32);
    r.addColorStop(0, 'rgba(255,255,255,1)'); r.addColorStop(.45, 'rgba(255,255,255,.85)'); r.addColorStop(1, 'rgba(255,255,255,0)');
    g.fillStyle = r; g.fillRect(0, 0, 64, 64);
    return new THREE.CanvasTexture(cv);
  })();
  const puntos = (pos, c, s, o) => {
    const g = new THREE.BufferGeometry(); g.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
    return new THREE.Points(g, new THREE.PointsMaterial({ color: c, size: s, map: punto, transparent: true, opacity: o, depthWrite: false, fog: true }));
  };

  const figuras = {
    // Primario: bloques para construir, apilados como una torre que crece.
    primario(c) {
      const g = new THREE.Group(), cubo = new THREE.BoxGeometry(1.6, 1.6, 1.6);
      const capas = [[0, 0], [1.9, 0], [-1.9, 0], [0, 1.9], [0, -1.9], [1.9, 1.9], [-1.9, -1.9]];
      capas.forEach(([x, z], i) => { const b = bordes(cubo, c, .7); b.position.set(x, -2.4, z); b.userData.base = b.position.clone(); g.add(b); });
      [[0, 0], [1.9, 0], [0, 1.9]].forEach(([x, z]) => { const b = bordes(cubo, c, .7); b.position.set(x, -.6, z); b.userData.base = b.position.clone(); g.add(b); });
      const tope = bordes(cubo, c, .9); tope.position.set(.95, 1.2, .95); tope.userData.base = tope.position.clone(); g.add(tope);
      const lapiz = bordes(new THREE.ConeGeometry(.8, 2.6, 6), c, .6); lapiz.position.set(.95, 3.3, .95); lapiz.rotation.z = Math.PI; g.add(lapiz);
      g.userData.giro = [0, .22, 0]; g.userData.bloques = true;
      return g;
    },
    // Secundario: red de ideas que se conectan alrededor de un libro abierto.
    secundario(c) {
      const g = new THREE.Group(), nodos = [];
      for (let i = 0; i < 30; i++) nodos.push(new THREE.Vector3().randomDirection().multiplyScalar(3.2 + Math.random() * 2.6));
      const seg = [];
      nodos.forEach((a, i) => nodos.forEach((b, j) => { if (j > i && a.distanceTo(b) < 3.4) seg.push(a.x, a.y, a.z, b.x, b.y, b.z); }));
      const lg = new THREE.BufferGeometry(); lg.setAttribute('position', new THREE.Float32BufferAttribute(seg, 3));
      g.add(new THREE.LineSegments(lg, linea(c, .45)));
      g.add(puntos(nodos.flatMap((v) => [v.x, v.y, v.z]), c, .55, .95));
      const hoja = new THREE.PlaneGeometry(2.4, 3.2, 1, 1);
      const izq = bordes(hoja, c, .8); izq.position.x = -1.2; izq.rotation.y = .5;
      const der = bordes(hoja, c, .8); der.position.x = 1.2; der.rotation.y = -.5;
      const libro = new THREE.Group(); libro.add(izq, der); g.add(libro);
      g.userData.giro = [.04, .18, 0];
      return g;
    },
    // Terciario: engranaje y circuito, la tecnicatura en tecnologias aplicadas.
    terciario(c) {
      const forma = new THREE.Shape(), T = 12, R1 = 3.4, R2 = 4.3;
      for (let i = 0; i <= T * 4; i++) {
        const a = (i / (T * 4)) * Math.PI * 2, r = Math.floor(i / 2) % 2 === 0 ? R2 : R1;
        i === 0 ? forma.moveTo(Math.cos(a) * r, Math.sin(a) * r) : forma.lineTo(Math.cos(a) * r, Math.sin(a) * r);
      }
      const hueco = new THREE.Path(); hueco.absarc(0, 0, 1.3, 0, Math.PI * 2, true); forma.holes.push(hueco);
      const geo = new THREE.ExtrudeGeometry(forma, { depth: 1, bevelEnabled: false, curveSegments: 20 }); geo.center();
      const g = new THREE.Group(), rueda = bordes(geo, c, .7); g.add(rueda);
      const chica = bordes(geo, c, .45); chica.scale.setScalar(.5); chica.position.set(4.6, 3.2, -.5); g.add(chica);
      const pistas = [];
      for (let i = 0; i < 6; i++) {
        const y = -4.6 + i * .5, x0 = -5.5 - (i % 3), x1 = -2.6;
        pistas.push(x0, y, 0, x1, y, 0, x1, y, 0, x1 + .8, y + .8, 0);
      }
      const pg = new THREE.BufferGeometry(); pg.setAttribute('position', new THREE.Float32BufferAttribute(pistas, 3));
      g.add(new THREE.LineSegments(pg, linea(c, .5)));
      g.rotation.y = -.35; g.userData.giro = [0, 0, 0]; g.userData.rueda = rueda; g.userData.chica = chica;
      return g;
    },
  };

  // ---- Que figuras van y donde ----
  const nivel = host.dataset.escena;                // 'portada' o el slug del nivel
  const slugs = nivel === 'portada' ? ['primario', 'secundario', 'terciario'] : [nivel];
  const separacion = 9;
  const estaciones = slugs.map((slug, i) => {
    const obj = figuras[slug](TONOS[slug]);
    const x = slugs.length === 1 ? 0 : (i - 1) * separacion;
    obj.position.set(x, 0, 0); obj.userData.casa = obj.position.clone(); obj.userData.slug = slug;
    obj.userData.escala = slugs.length === 1 ? 1 : .7; obj.scale.setScalar(obj.userData.escala);
    scene.add(obj);
    return obj;
  });

  const N = chico ? 500 : 1100, polvo = [];
  for (let i = 0; i < N; i++) polvo.push((Math.random() - .5) * 80, (Math.random() - .5) * 40, 10 - Math.random() * 70);
  const nube = puntos(polvo, 0xc9d8f0, .12, .45); scene.add(nube);

  // ---- Camara: en la portada recorre las tres figuras; en un nivel mira la suya ----
  const ancho = () => caja().width, alto = () => caja().height;
  let foco = -1, enfoque = 0, mx = 0, my = 0, smx = 0, smy = 0, scrollF = 0;
  const camBase = new THREE.Vector3(), mira = new THREE.Vector3();

  // Desplazamiento de camara para que un punto quede en la fraccion f del ancho (0 izquierda, 1 derecha).
  function corrimiento(z, f) {
    const mitad = z * Math.tan((camera.fov / 2) * Math.PI / 180) * camera.aspect;
    return (f - 0.5) * 2 * mitad;
  }
  function objetivo() {
    const amplio = ancho() > 900;
    if (slugs.length === 1) {
      // La figura queda a la derecha de la portada, detras del texto en pantallas chicas.
      const d = amplio ? corrimiento(17, .72) : 0;
      camBase.set(-d, 1, 17); mira.set(-d, 0, 0);
      return;
    }
    // En escritorio el trio ocupa la mitad derecha, por encima de los accesos;
    // la figura elegida se acerca pero sigue a la derecha, lejos del titulo.
    if (foco >= 0) {
      const p = estaciones[foco].userData.casa, z = 24, d = amplio ? corrimiento(z, .74) : 0;
      camBase.set(p.x - d, 1.5, z); mira.set(p.x - d, -2.5, 0);
    } else {
      const z = amplio ? 36 : 44, d = amplio ? corrimiento(z, .69) : 0;
      camBase.set(-d, 3, z); mira.set(-d, -4, 0);
    }
  }

  // Los accesos de la portada guian la camara.
  document.querySelectorAll('[data-nivel-acceso]').forEach((el) => {
    const i = slugs.indexOf(el.dataset.nivelAcceso);
    const entrar = () => { foco = i; objetivo(); };
    const salir = () => { foco = -1; objetivo(); };
    el.addEventListener('pointerenter', entrar); el.addEventListener('focus', entrar);
    el.addEventListener('pointerleave', salir); el.addEventListener('blur', salir);
  });
  window.addEventListener('pointermove', (e) => {
    mx = (e.clientX / window.innerWidth) * 2 - 1; my = (e.clientY / window.innerHeight) * 2 - 1;
  }, { passive: true });
  window.addEventListener('scroll', () => { scrollF = clamp(window.scrollY / Math.max(1, alto()), 0, 1); }, { passive: true });

  const cam = new THREE.Vector3(), look = new THREE.Vector3();
  function medir() {
    renderer.setSize(ancho(), alto(), false);
    camera.aspect = ancho() / Math.max(1, alto()); camera.updateProjectionMatrix();
    objetivo();
  }
  medir(); cam.copy(camBase).add(new THREE.Vector3(0, 6, 22)); look.copy(mira);
  window.addEventListener('resize', medir);

  let activo = true, visible = true, ultimo = performance.now();
  function cuadro(ahora) {
    if (!activo) return;
    const dt = Math.min(.05, (ahora - ultimo) / 1000); ultimo = ahora;
    const t = ahora / 1000;
    smx += (mx - smx) * Math.min(1, dt * 2.5); smy += (my - smy) * Math.min(1, dt * 2.5);
    cam.lerp(camBase, Math.min(1, dt * 1.8)); look.lerp(mira, Math.min(1, dt * 2.2));
    camera.position.set(cam.x + smx * 1.6, cam.y - smy * 1 + scrollF * 4, cam.z + scrollF * 6);
    camera.lookAt(look);

    enfoque += ((foco >= 0 ? 1 : 0) - enfoque) * Math.min(1, dt * 3);
    estaciones.forEach((s, i) => {
      const elegido = foco === i, apagado = foco >= 0 && !elegido;
      const v = s.userData.giro, k = elegido ? 2.2 : 1;
      s.rotation.x += v[0] * dt * k; s.rotation.y += v[1] * dt * k; s.rotation.z += v[2] * dt * k;
      s.position.y = s.userData.casa.y + Math.sin(t * .7 + i * 1.3) * .4;
      const meta = s.userData.escala * (elegido ? 1.3 : 1);
      s.scale.setScalar(s.scale.x + (meta - s.scale.x) * Math.min(1, dt * 4));
      s.traverse((o) => { if (o.material) { o.material.userData.o ??= o.material.opacity; o.material.opacity = o.material.userData.o * (apagado ? .35 : 1); } });
      if (s.userData.rueda) { s.userData.rueda.rotation.z += dt * .5; s.userData.chica.rotation.z -= dt * 1; }
      if (s.userData.bloques) s.children.forEach((b, j) => { if (b.userData.base) b.position.y = b.userData.base.y + Math.max(0, Math.sin(t * 1.4 + j)) * (elegido ? .35 : .12); });
    });
    nube.rotation.y += dt * .01;

    renderer.render(scene, camera);
    requestAnimationFrame(cuadro);
  }

  // Solo dibuja mientras la portada esta a la vista: ahorra bateria en celulares.
  function pausar() { activo = false; }
  function seguir() { if (!activo && visible && !document.hidden) { activo = true; ultimo = performance.now(); requestAnimationFrame(cuadro); } }
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(([e]) => { visible = e.isIntersecting; visible ? seguir() : pausar(); }).observe(host);
  }
  document.addEventListener('visibilitychange', () => (document.hidden ? pausar() : seguir()));

  requestAnimationFrame(cuadro);
  requestAnimationFrame(() => host.classList.add('escena--lista'));
}

iniciar();

// ---------------------------------------------------------------------
// Sentido de la transicion entre paginas: hacia un nivel mayor gira a la
// izquierda, hacia uno menor a la derecha. La portada cuenta como 0.
// ---------------------------------------------------------------------
const ORDEN = { '': 0, primario: 1, secundario: 2, terciario: 3 };
const ordenDe = (url) => ORDEN[new URL(url, location.href).pathname.replace(/^\/|\/$/g, '')] ?? null;
window.addEventListener('pageswap', (e) => {
  if (!e.viewTransition || !e.activation || !e.activation.entry) return;
  const desde = ordenDe(location.href), hacia = ordenDe(e.activation.entry.url);
  if (desde === null || hacia === null || desde === hacia) return;
  e.viewTransition.types.add(hacia > desde ? 'adelante' : 'atras');
});
window.addEventListener('pagereveal', (e) => {
  if (!e.viewTransition || !navigation.activation || !navigation.activation.from) return;
  const desde = ordenDe(navigation.activation.from.url), hacia = ordenDe(location.href);
  if (desde === null || hacia === null || desde === hacia) return;
  e.viewTransition.types.add(hacia > desde ? 'adelante' : 'atras');
});
