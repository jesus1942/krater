@php($inst = \Crater\Support\PublicSite::institution())
<section class="seccion seccion--gris" id="contacto" aria-labelledby="titulo-contacto">
    <div class="contenedor contacto aparece">
        <div>
            <p class="volanta">Contacto</p>
            <h2 id="titulo-contacto" class="titulo-seccion">Dónde encontrarnos</h2>
            <dl class="datos">
                <div><dt>Dirección</dt><dd><a href="{{ $inst['map'] }}" target="_blank" rel="noopener">{{ $inst['address'] }} · {{ $inst['city'] }}</a></dd></div>
                <div><dt>Teléfono</dt><dd><a href="tel:{{ $inst['phone_link'] }}">{{ $inst['phone'] }}</a></dd></div>
                <div><dt>Correo</dt><dd><a href="mailto:{{ $inst['email'] }}">{{ $inst['email'] }}</a></dd></div>
                <div><dt>Instagram</dt><dd><a href="https://instagram.com/{{ $inst['instagram'] }}" target="_blank" rel="noopener">&#64;{{ $inst['instagram'] }}</a></dd></div>
            </dl>
        </div>
        <a class="mapa" href="{{ $inst['map'] }}" target="_blank" rel="noopener" aria-label="Ver {{ $inst['address'] }} en el mapa">
            <span class="mapa__pin" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0119 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
            </span>
            <span class="mapa__direccion">{{ $inst['address'] }}</span>
            <span class="mapa__ciudad">{{ $inst['city'] }}</span>
            <span class="mapa__ir">Abrir en el mapa</span>
        </a>
    </div>
</section>
