<template>
  <footer
    class="ena-footer absolute bottom-0 flex items-center justify-end w-full h-14 px-4 md:px-8"
  >
    <a
      href="/admin/dashboard"
      class="ena-footer__brand flex items-center gap-3"
      aria-label="Escuela Nueva Austral"
    >
      <img src="/images/ena-owl.svg" alt="" class="ena-footer__owl" />
      <span class="ena-footer__powered">Impulsado por</span>
      <strong>Escuela Nueva Austral</strong>
    </a>
  </footer>
</template>

<style lang="scss" scoped>
.ena-footer {
  overflow: hidden;
  background:
    radial-gradient(circle at 88% 0%, rgba(165, 18, 28, 0.13), transparent 28%),
    linear-gradient(90deg, #fff 0%, #f4f0e7 62%, #e7ded0 100%);
  border-top: 4px solid #102340;
  box-shadow: inset 0 -6px 0 #a5121c;
  color: #102340;
}

.ena-footer__brand {
  min-height: 38px;
  padding: 4px 12px 8px;
  border-left: 3px solid #102340;
  text-transform: uppercase;
  letter-spacing: 0.055em;
  line-height: 1;
  transition: transform 0.25s ease, color 0.25s ease;
}

.ena-footer__brand:hover {
  color: #a5121c;
  transform: translateX(-4px);
}

.ena-footer__owl {
  width: 22px;
  height: 32px;
  object-fit: contain;
}

.ena-footer__powered {
  font-size: 10px;
  font-weight: 700;
}

.ena-footer__brand strong {
  font-size: 12px;
  font-weight: 900;
}

@media (max-width: 640px) {
  .ena-footer__powered {
    display: none;
  }

  .ena-footer__brand strong {
    font-size: 10px;
  }
}
</style>
