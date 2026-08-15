<template>
  <div class="ena-login grid h-full grid-cols-12 overflow-y-hidden">
    <base-notification />
    <div
      class="ena-login__panel flex items-center justify-center w-full col-span-12 p-6 text-gray-900 md:p-10 md:col-span-6 lg:col-span-4 flex-2"
    >
      <div class="ena-login__content w-full max-w-sm">
        <a href="/admin" class="ena-login__brand block">
          <img
            src="/images/ena-logo.svg"
            alt="Escuela Nueva Austral"
            class="block w-full"
          />
        </a>
        <router-view></router-view>
        <div
          class="pt-16 mt-0 text-sm not-italic font-medium leading-relaxed text-left text-gray-500"
        >
          <p class="mb-3">{{ $t('layout_login.copyright_crater') }}</p>
        </div>
      </div>
    </div>

    <div
      class="relative flex-col items-center justify-center hidden w-full h-full pl-10 bg-no-repeat bg-cover md:col-span-6 lg:col-span-8 md:flex content-box"
    >
      <div class="ena-login__aurora" />
      <img src="/images/ena-owl.svg" alt="" class="ena-login__owl" />
      <div class="ena-login__copy pl-16 xl:pl-0">
        <span class="ena-login__eyebrow">Suite institucional · ENA</span>
        <h1
          class="hidden mb-3 text-3xl font-bold leading-normal text-white xl:text-5xl xl:leading-tight md:none lg:block"
        >
          Una gestión clara para <br />
          toda la comunidad <br />
          educativa <br />
        </h1>
        <p
          class="hidden text-sm not-italic font-normal leading-normal text-gray-100 xl:text-base xl:leading-6 md:none lg:block"
        >
          Administración, cobranzas y seguimiento institucional<br />
          de Escuela Nueva Austral en un solo lugar.<br />
        </p>
      </div>

      <div class="absolute z-50 w-full bg-no-repeat content-bottom" />
    </div>
  </div>
</template>

<style lang="scss" scoped>
.content-box {
  isolation: isolate;
  overflow: hidden;
  background:
    radial-gradient(circle at 78% 24%, rgba(165, 18, 28, 0.3), transparent 28%),
    radial-gradient(circle at 28% 82%, rgba(244, 240, 231, 0.12), transparent 32%),
    linear-gradient(135deg, #07101d 0%, #102340 52%, #293e5a 100%);
}

.content-bottom {
  height: 220px;
  right: 0;
  bottom: -90px;
  background: linear-gradient(90deg, transparent, rgba(165, 18, 28, 0.55));
  filter: blur(52px);
  transform: rotate(-8deg);
}

.content-box::before {
  content: '';
  width: 58%;
  height: 140%;
  right: -18%;
  position: absolute;
  top: -20%;
  z-index: -1;
  background: linear-gradient(180deg, rgba(244, 240, 231, 0.12), transparent 72%);
  transform: rotate(14deg);
  border-left: 1px solid rgba(244, 240, 231, 0.18);
}

.content-box::after {
  content: '';
  width: 260px;
  height: 260px;
  right: 8%;
  top: 12%;
  position: absolute;
  border: 1px solid rgba(244, 240, 231, 0.16);
  border-radius: 50%;
  box-shadow: 0 0 90px rgba(165, 18, 28, 0.22);
}

.ena-login {
  background: #f4f0e7;
}

.ena-login__panel {
  position: relative;
  background:
    radial-gradient(circle at 4% 10%, rgba(165, 18, 28, 0.08), transparent 24%),
    linear-gradient(145deg, #fff 0%, #f4f0e7 70%, #e9e3d8 100%);
}

.ena-login__panel::after {
  content: '';
  position: absolute;
  top: 0;
  right: -90px;
  width: 180px;
  height: 100%;
  background: linear-gradient(90deg, rgba(244, 240, 231, 0), #102340);
  opacity: 0.18;
  filter: blur(32px);
  pointer-events: none;
}

.ena-login__content {
  position: relative;
  z-index: 2;
  animation: ena-rise 0.7s ease-out both;
}

.ena-login__brand {
  width: min(100%, 330px);
  margin-bottom: 3.5rem;
  filter: drop-shadow(0 18px 24px rgba(16, 35, 64, 0.12));
  transition: transform 0.35s ease, filter 0.35s ease;
}

.ena-login__brand:hover {
  transform: translateY(-3px);
  filter: drop-shadow(0 22px 28px rgba(16, 35, 64, 0.2));
}

.ena-login__aurora {
  position: absolute;
  width: 520px;
  height: 520px;
  right: -100px;
  top: 12%;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(244, 240, 231, 0.2), transparent 66%);
  filter: blur(8px);
  animation: ena-pulse 7s ease-in-out infinite;
}

.ena-login__owl {
  position: absolute;
  z-index: 1;
  right: 5%;
  bottom: -7%;
  width: min(34vw, 390px);
  opacity: 0.2;
  filter: drop-shadow(0 40px 35px rgba(0, 0, 0, 0.3));
  transform: rotate(7deg);
}

.ena-login__copy {
  position: relative;
  z-index: 3;
  animation: ena-rise 0.8s 0.12s ease-out both;
}

.ena-login__eyebrow {
  display: inline-block;
  margin-bottom: 1.5rem;
  padding-bottom: 0.5rem;
  color: #f4f0e7;
  border-bottom: 3px solid #a5121c;
  font-size: 0.75rem;
  font-weight: 800;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}

@keyframes ena-rise {
  from { opacity: 0; transform: translateY(16px); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes ena-pulse {
  0%, 100% { opacity: 0.55; transform: scale(0.96); }
  50% { opacity: 1; transform: scale(1.04); }
}

@media (prefers-reduced-motion: reduce) {
  .ena-login__content,
  .ena-login__copy,
  .ena-login__aurora {
    animation: none;
  }
}
</style>
