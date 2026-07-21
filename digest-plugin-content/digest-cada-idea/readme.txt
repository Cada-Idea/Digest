=== Digest by Cada Idea ===
Contributors: cadaidea
Tags: newsletter, email, subscribers, campaigns, woocommerce, popup
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Te dejo que inspires. Sistema completo de boletines para WordPress: suscriptores, listas, campañas, automatizaciones de posts y productos, popups y widgets. Sin límites de envío.

== Description ==

Digest by Cada Idea es un plugin de email marketing 100% auto-alojado, sin servicios externos obligatorios y sin límites de envío.

**Características v1.0:**

* Gestión de suscriptores con estados (pendiente, confirmado, dado de baja, bounced)
* Listas múltiples con asignación libre
* Doble opt-in con plantilla editable
* Correo de bienvenida opcional
* Formulario de suscripción vía shortcode `[boletines_form]`
* Editor de campañas con TinyMCE y bloques rápidos (titular, párrafo, botón CTA, imagen, separador, firma)
* Programación de campañas
* Motor de envío con cron, lotes y control de velocidad
* Tracking de aperturas (pixel 1x1) y clics (link rewriting)
* One-Click Unsubscribe (RFC 8058) — cumple requisitos Gmail/Yahoo 2024
* Integración nativa con WooCommerce (casilla en checkout)
* REST API para acciones del admin
* Compatible con cualquier plugin SMTP: FluentSMTP, WP Mail SMTP, Post SMTP, etc.

**No tiene servicio propio de envío.** Usa `wp_mail()` por debajo, así que tú eliges:

* Tu propio SMTP / hosting
* Amazon SES (~$0.10 por 1.000 correos)
* Brevo (300/día gratis)
* SendGrid, Mailgun, Postmark, Zoho ZeptoMail, etc.

== Installation ==

1. Sube la carpeta `boletines/` a `/wp-content/plugins/`
2. Actívalo desde Plugins
3. Ve a *Boletines → Ajustes* y configura el remitente
4. Instala FluentSMTP (recomendado) y conéctalo a tu proveedor
5. Crea tu primera lista, pega el shortcode `[boletines_form]` en una página
6. ¡Empieza a enviar!

== Changelog ==

= 2.2.0 — Auto-actualización desde cadaidea.com =
* **🔄 Sistema de auto-actualización**: el plugin ahora se actualiza automáticamente desde cadaidea.com sin necesidad de intervención manual.
* **🩺 Nueva pestaña "Test de URLs" en Digest → Salud**: sin enviar emails, puedes ver y probar las URLs que el plugin genera para cada suscriptor. Muestra el formato path-based (recomendado) y el legacy con query string, y verifica que las rewrite rules estén registradas. Imprescindible para diagnosticar problemas con WAFs/CDNs que pelan query strings.
* **Toolbox de diagnóstico embebida en la página de error**: cuando un admin ve "Falta el identificador del suscriptor", ahora ve REQUEST_URI, QUERY_STRING y HTTP_HOST para detectar de un vistazo si el WAF está mutilando la URL.

= 2.1.1 — Fix enlaces de preferencias / unsubscribe / confirmación =
* **🔗 URLs de correo migradas a path-based**: los links ahora son `/boletines-action/preferences/{sid}/{token}/` en lugar de `/?boletines_action=preferences&sid=...&token=...`. Esto arregla el bug donde Cloudflare, Sucuri, Wordfence u otros WAFs pelaban los parámetros del query string y dejaban el link roto con el error "Falta el identificador del suscriptor en la URL". El formato viejo sigue funcionando como fallback para emails en circulación.
* **🩺 Mensaje de error diagnóstico**: cuando un admin ve el error de "falta el identificador", ahora ve también `REQUEST_URI`, `QUERY_STRING` y `HTTP_HOST` para detectar de un vistazo si el WAF está pelando parámetros.
* **⚙️ Opción legacy**: nuevo setting `force_legacy_query_strings` (en Ajustes) por si algún hosting exótico sigue necesitando el formato viejo.
* **🔄 Reescritura de URLs en install/activate**: se registran las nuevas rewrite rules y se hace flush automático.

= 1.9.2 — Versión candidata a distribución =
* **Wizard de licencia simplificado**: eliminado el panel técnico de diagnóstico del flujo de activación (HTTP codes, rest_no_route, etc.). El usuario final ahora ve un flujo limpio: nombre, apellido, email, cumpleaños → "Enviarme código" → llega un email → introduce el código → listo.
* **Diagnóstico de conectividad movido a Salud**: la antigua pestaña "SMTP" en Digest → Salud se renombra a "Conectividad" y ahora combina dos pruebas: envío de correos (SMTP saliente) Y conexión con el servidor de licencias. Útil tanto para diagnóstico como para soporte.
* **Constante BOLETINES_LICENSE_API_BASE** disponible para developers: permite apuntar el plugin a un servidor de licencias alternativo (útil para sandbox/testing). Definir en wp-config.php. Por defecto apunta a cadaidea.com.
* **Filtro `boletines_license_api_base`** equivalente a la constante, para casos donde se necesite cambio dinámico.

= 1.9.1 — Estabilidad, snapshots automáticos y página de Salud =
* **🛡 Snapshots automáticos antes de cada migración**: el plugin guarda un volcado SQL completo de las tablas críticas (suscriptores, listas, campañas, formularios, automatizaciones, bounces…) en `/wp-content/uploads/digest-backups/` antes de cualquier actualización de versión. Si algo va mal en la migración, puedes restaurar en un click desde Digest → Salud.
* **🔍 Página "Salud" nueva**: diagnóstico completo del plugin con 4 pestañas:
   * **Diagnóstico**: detecta tablas faltantes, tablas vacías tras migración (sospechoso), opciones huérfanas, crons no programados.
   * **Snapshots**: lista, crea, descarga, restaura o borra snapshots automáticos del plugin (se conservan los últimos 5).
   * **SMTP**: detecta automáticamente plugins SMTP instalados (FluentSMTP, WP Mail SMTP, Easy WP SMTP, Post SMTP, etc.), avisa de conflictos, ofrece prueba rápida de envío e instalación directa de FluentSMTP si no hay ninguno.
   * **Sistema**: info técnica, estado de cada tabla con conteo de filas en tiempo real, hooks para developers.
* **⚠ Banner persistente en el admin** si detecta inconsistencia crítica (ej: tabla vacía en instalación que no es nueva). Antes esto se enmascaraba con la "Lista principal" creada por defecto.
* **🔒 Fix de seguridad anti-corrupción**: el seeding de la "Lista principal" por defecto ahora SÓLO se ejecuta en instalaciones nuevas reales. Antes, si por cualquier motivo la tabla de listas aparecía vacía en una instalación existente, se enmascaraba el problema. Ahora se muestra la inconsistencia y se ofrece restaurar snapshot.
* **📝 Log de migraciones**: se guarda en option `boletines_installer_log` con timestamps de cada paso para diagnóstico forense.
* **🕒 Tracking de primera instalación**: nueva option `boletines_first_install` con la fecha de la primera activación del plugin. Permite distinguir instalaciones nuevas de existentes.
* **Rebrand**: "Digest Cada Idea" → "Digest by Cada Idea". Menú lateral de WP muestra simplemente "Digest". Slug interno `boletines`, prefijo de tabla `bol_`, options y text-domain se mantienen idénticos → CERO migración de datos requerida.
* **Sistema modular de Addons**: nueva página "Digest → ✨ Addons" que centraliza los módulos Pro del plugin (de momento sólo Automatizaciones, más en versiones futuras). Filtro `boletines_modules` para extensiones de terceros.

= 1.8.1 — Fix botones de variables en footer =
* Los 6 botones de variables (`{first_name}`, `{last_name}`, `{full_name}`, `{site_name}`, `{site_url}`, `{current_year}`) ahora muestran el código literal en monospace en lugar de etiquetas en español. Más obvio qué inserta cada uno. Layout cambiado de flexbox a `inline-block` con `line-height` para mayor compatibilidad con themes admin que sobreescriben CSS de flexbox.

= 1.8.0 — Pro gating + footer mejorado =
* **Gating Pro de automatizaciones**: el plugin sigue siendo gratis para todo lo básico (suscriptores, listas, campañas manuales, formularios, popups, widgets, branding del correo). Las automatizaciones (post-publicado, producto-publicado, digest diario/semanal, correos de cumpleaños) ahora requieren licencia activa. Puedes configurarlas y guardarlas sin licencia, pero no se ejecutan. Banner amarillo en la lista de Automatizaciones lo deja claro.
* **Multi-dominio con licencia**: la misma licencia Pro funciona en todos tus sitios (bletia.ec, seridea.ec, netvez.com, talutil.com, etc.) gracias a la API de Readers Cada Idea, que añade automáticamente cada nuevo sitio a la lista vinculada del Reader.
* **Footer del correo: pegado de URLs sin HTML**: ya no necesitas escribir HTML para poner enlaces en el pie. Si pegas una URL como `https://cadaidea.com/blog`, se convierte automáticamente en enlace al enviar. Cambio en `Mailer\Branding::autolink_urls`.
* **Variables insertables en el footer**: 6 botones que insertan al vuelo `{first_name}`, `{last_name}`, `{full_name}`, `{site_name}`, `{site_url}`, `{current_year}` en el textarea — basta hacer clic, sin tipear las llaves.
* **Nuevo campo "Enlaces del pie"**: en Ajustes → Branding correos, un textarea adicional con formato `Etiqueta|URL` por línea, mismo patrón que las redes sociales pero renderizado entre el texto del pie y el aviso de baja, separados por · . Útil para "Blog · Contacto · Privacidad · Términos".
* **Wizard de Licencia más claro**: explica exactamente qué incluye Pro (automatizaciones + multi-dominio) y qué sigue funcionando libre. Banner global en pantallas del plugin también actualizado.
* **Auto-suscripción a lista Digest**: en cadaidea.com (con el addon `digest-license.php` instalado en Cada Idea Readers), cada usuario que active Pro se suscribe automáticamente a una lista que el admin elige. El cumpleaños del nuevo reader también se guarda como meta del suscriptor — listo para automatizaciones de cumpleaños.

= 1.7.0 — Rebrand a Digest Cada Idea =
* **Rebrand**: el plugin pasa a llamarse "Digest Cada Idea". Autor: Cada Idea (https://cadaidea.com). Plugin URI: https://cadaidea.com/digest. Slug interno "boletines" se mantiene para compatibilidad de tablas y opciones — ninguna instalación existente se rompe.
* **Sistema de activación opcional**: nueva pantalla "Digest → Licencia" con asistente de 3 pasos (datos → código por email → activado). Conecta con el plugin "Readers Cada Idea" en cadaidea.com vía 3 endpoints REST (register / activate / verify). La activación es OPCIONAL — el plugin funciona al 100% sin licencia. Activar da updates más rápidos, soporte y futuras features Pro. Cumple con la política de WordPress.org.
* **Bug fix gestión de preferencias**: mensajes de error específicos para diagnosticar exactamente qué falla en el enlace (falta sid / falta token / suscriptor no existe / token vacío / token no coincide). Antes mostraba siempre "Enlace inválido" sin pista de qué corregir.
* **Pie de correo con preferencias en TODOS los envíos**: refactor de Branding — ahora basta pasarle el subscriber para que genere automáticamente los links de "Gestionar preferencias" y "Darme de baja". Aplica a campañas, confirmaciones de doble opt-in, automatizaciones RSS, digest y cumpleaños — sin código duplicado.
* **Texto custom en el pie del correo**: nuevo campo "Texto extra del pie" en Ajustes → Branding correos. HTML permitido (enlaces, &lt;br&gt;, &lt;strong&gt;). Se muestra centrado entre la línea de redes sociales y el aviso de baja. Perfecto para "Visita nuestro blog", "Política de privacidad", "Contacto".
* **Widget minimalista con campo nombre**: el widget ahora soporta el toggle "Mostrar nombre" — pill horizontal con nombre + email + botón. Responsive:
  * PC (≥1024px): horizontal en una línea
  * iPad (601–1024px): horizontal con flex 1 1 140px
  * Móvil (≤600px): se apila verticalmente
  * Móvil estrecho (≤380px): incluso sin nombre se apila
* **Import/Export completo de TODOS los campos**: CSV ahora incluye phone, birthday y listas. Import detecta columnas en español e inglés (phone/whatsapp/teléfono/celular/móvil; birthday/cumpleaños/fecha_nacimiento). Acepta formato YYYY-MM-DD o DD/MM/YYYY. Export incluye lista de suscripciones separada por |.
* **Documentación de API**: `includes/Licensing/Client.php` documenta los 3 endpoints exactos que tu plugin "Readers Cada Idea" tiene que exponer en cadaidea.com para que la activación funcione end-to-end.

= 1.6.0 =
* **Bug fix imagen y categoría en automatizaciones**: cambiado el hook de `transition_post_status` a `wp_after_insert_post` (WP 5.6+). El primero se dispara ANTES de que Gutenberg guarde la imagen destacada y los términos (peticiones REST separadas), por eso los correos salían con imagen vacía y la categoría por defecto. El nuevo hook se dispara DESPUÉS, garantizando datos completos.
* **Bug fix página de preferencias**: el JS standalone se cargaba antes que `window.BoletinesPreferences`, así que la página no respondía. Arreglado el orden y simplificado el HTML.
* **Forms responsive a fondo**: touch targets mínimo 44px, font-size con `clamp()`, breakpoints adicionales (≤480px y ≤360px), el campo WhatsApp se apila en pantallas muy estrechas. Inputs heredan font-family y color del tema vía `inherit` y `currentColor`.
* **Custom fields por suscriptor** (nueva infraestructura `bol_subscriber_meta`): clave-valor por suscriptor extensible.
* **Campo WhatsApp**: nuevo toggle en formularios. Selector de país con prefijos hispanohablantes (Ecuador, España, México, Colombia, Argentina, Perú, Venezuela, Chile, Bolivia, Paraguay, Uruguay, Guatemala, Honduras, Nicaragua, Costa Rica, Panamá, El Salvador, Cuba) + EE.UU. Ecuador es el default. Normalización automática a E.164.
* **Campo cumpleaños**: input HTML5 date. Se guarda como meta.
* **Automatización "Email de cumpleaños"**: nuevo tipo. Cron diario detecta quién cumple hoy (con meta `birthday`) y envía un correo personalizado. Asunto y cuerpo configurables con variables `{first_name}`, `{site_name}`. Una sola vez por persona por día. Funciona con cualquier lista filtrada o todas.

= 1.5.0 =
* **Bug fix popup**: el JS de disparadores se enqueueaba demasiado tarde (en wp_footer prio 50, pero los scripts del footer se imprimen en prio 20). Ahora se detecta y encola en wp_enqueue_scripts y los datos se pasan vía wp_localize_script. Popup, slide-in, exit-intent y barras funcionan como deben.
* **Plantillas eliminadas**: el sistema de plantillas se quita por completo (no se usaba). En su lugar, los bloques dinámicos (Posts, Productos, Populares, Top) cubren la necesidad real.
* **Branding global de correos**: en Ajustes → Branding correos puedes definir UN logo + ancho que se usa en TODOS los correos del plugin (confirmaciones, campañas, automatizaciones), color de marca para enlaces, página personalizada de "Gestionar preferencias", y especialmente la línea de redes sociales del pie (formato Etiqueta|URL, una por línea, aparecen separadas por | en el correo).
* **Productos WooCommerce en automatizaciones**: nuevos tipos "Al publicar un producto" y digest de productos. Filtra por categorías de producto. Layout 2 columnas (imagen | título-meta-extracto-botón) con responsive (en móvil colapsa a 1 columna).
* **Bloques dinámicos en campañas**: nuevos shortcodes embebibles que se procesan al enviar:
  - [boletines_posts ids="1,2,3"] o por categoría
  - [boletines_popular_posts count="3" days="30"]
  - [boletines_products ids/category]
  - [boletines_top_products by="rating|sales"]
  En el editor de campañas, botones "+ Posts", "+ Posts populares", "+ Productos", "+ Top productos" insertan el shortcode.
* **Página de gestión de suscripción** estilo Newsletters: nuevo shortcode [boletines_preferences] para que el usuario marque/desmarque listas con toggles, "Suscribir a todo", o darse de baja completa. Si no hay página configurada, el plugin sirve una standalone automáticamente.
* **Widget minimalista** (nuevo tipo de form): pill compacta email + botón pensada para sidebar/footer. Mismo shortcode [boletines_form id="N"].
* **Pie de correo unificado**: ahora vive en un solo sitio (Mailer\Branding) y se aplica idéntico a todos los envíos. Footer con redes (línea con |) + bloque legal con Gestionar preferencias y Darse de baja.

= 1.4.0 =
* Forms 2.0: nuevo gestor de formularios con tipos múltiples — inline, popup con disparador temporal, exit-intent, slide-in, barra fija arriba/abajo, y "después del contenido". Cada uno con reglas de visualización, frecuencia por visitante (cookie), límite de apariciones y disparadores configurables.
* Tracking de impresiones y conversiones por formulario, con tasa de conversión visible en la lista.
* Plugin theme-adaptive: el formulario hereda colores del tema vía CSS variables y currentColor + color-mix con fallbacks. Se ve bien en temas claros y oscuros automáticamente.
* Detección automática del color principal del tema (theme.json en block themes; editor-color-palette en clásicos). Botón usa ese color por defecto, o el que configures manualmente en Ajustes → Estilo formularios.
* Eliminado completamente el sistema de Tags (no se usaba). Las tablas se eliminan automáticamente al actualizar.
* Nuevo shortcode con id: `[boletines_form id="N"]` renderiza un formulario guardado.

= 1.3.0 =
* Formulario rediseñado: envío AJAX (sin recarga), feedback inline tipo "Casi listo, revisa tu correo".
* Layout nuevo: nombre y apellido en la misma fila, correo debajo.
* Modo "picker" automático: si hay varias listas públicas, se muestran como tarjetas seleccionables con icono.
* Imagen e icono SVG por lista (mediateca o SVG inline saneado).
* Estilo limpio con CSS variables — hereda colores del tema.
* Versión visible como badge en la cabecera de cada página del admin.
* Bug fix: el campo de redirect del formulario ya no usa `wp_get_referer()` (quedaba fuera de servicio con caché).

= 1.2.0 =
* Reportes con gráficos SVG inline por campaña: aperturas/clics por hora (24h), top enlaces, KPIs (open rate, click rate, CTOR, bounced, bajas).
* Automatizaciones: "Al publicar post" (RSS-to-email instantáneo) y digests diario/semanal con plantilla envoltorio opcional.
* Detección de bounces: webhook universal para SES/SNS, Mailgun, Postmark, etc., con secret rotable. Política configurable de auto-baja en hard bounces y umbral de soft bounces.
* Pantalla "Bounces" con KPIs, log filtrable y guía de configuración por proveedor.
* Detección local de bounces cuando wp_mail falla con códigos 5.x (sin necesidad de webhook).
* Cron diario para automatizaciones de digest.

= 1.1.0 =
* Tags con color para etiquetar suscriptores y combinarlos con listas.
* Segmentación AND/OR en campañas (listas + tags).
* Importar/Exportar CSV con detección de delimitador y mapeo flexible de columnas.
* Plantillas de campaña reutilizables.
* Sincronización con usuarios de WordPress (auto + masiva).
* Bloque Gutenberg "Boletines › Formulario".

= 1.0.0 =
* Versión inicial: suscriptores, listas, campañas, envío con cron, tracking, doble opt-in, integración WooCommerce.
