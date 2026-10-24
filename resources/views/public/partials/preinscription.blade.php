@php($inst = \Crater\Support\PublicSite::institution())
@php($contact = $contact ?? $inst)
<section class="seccion" id="preinscripcion" aria-labelledby="titulo-preinscripcion">
    <div class="contenedor">
        <div class="banda aparece">
            <div>
                <p class="volanta volanta--clara">Ciclo lectivo 2027</p>
                <h2 id="titulo-preinscripcion" class="banda__titulo">Preinscripción abierta</h2>
                <p class="banda__texto">Consultá vacantes en Primario y Secundario, y sumate a la lista de interesados de la Tecnicatura Superior en Tecnologías Aplicadas.</p>
            </div>
            <div class="banda__botones">
                <a class="boton boton--claro" href="https://wa.me/{{ $contact['whatsapp'] }}?text={{ rawurlencode('Hola, quiero información sobre la preinscripción 2027') }}" target="_blank" rel="noopener">Escribinos por WhatsApp</a>
                <a class="boton boton--borde-claro" href="mailto:{{ $contact['email'] }}?subject={{ rawurlencode('Preinscripción 2027') }}">Escribinos por correo</a>
            </div>
        </div>
    </div>
</section>
