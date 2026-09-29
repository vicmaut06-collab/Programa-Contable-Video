const MAX_LINEAS = 50;

function formato(valor) {
  const n = parseFloat(valor) || 0;
  return '$ ' + n.toLocaleString('es-SV', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function apiCuentas(params) {
  const base = document.querySelector('link[href*="assets/css/estilos.css"]');
  const prefijo = base ? base.getAttribute('href').replace('assets/css/estilos.css', '') : '';
  return fetch(prefijo + 'api/cuentas.php?' + new URLSearchParams(params).toString(), {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  }).then(r => r.json());
}

function valorNumerico(input) {
  return parseFloat(String(input ? input.value : '').replace(/,/g, '')) || 0;
}

/* ---------- Campos de importe ---------- */

function campoNumerico(input, contraparte) {
  input.addEventListener('input', () => {
    let v = input.value.replace(/[^0-9.]/g, '');
    const partes = v.split('.');
    if (partes.length > 2) v = partes.shift() + '.' + partes.join('.');
    input.value = v;

    // Solo se escribe en un lado: al llenar Debe se limpia Haber y viceversa
    if (parseFloat(v) > 0 && contraparte) contraparte.value = '';

    marcarPartida(input.closest('.partida'));
    recalcularTotales();
  });

  input.addEventListener('blur', () => {
    const n = parseFloat(input.value) || 0;
    input.value = n > 0 ? n.toFixed(2) : '';
    marcarPartida(input.closest('.partida'));
    recalcularTotales();
  });

  input.addEventListener('keydown', e => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    saltarAlSiguienteCampo(input);
  });
}

function saltarAlSiguienteCampo(input) {
  const partida = input.closest('.partida');
  const campos = Array.from(partida.querySelectorAll('select, input')).filter(c => !c.disabled);
  const pos = campos.indexOf(input);
  if (pos > -1 && pos < campos.length - 1) {
    campos[pos + 1].focus();
    campos[pos + 1].select();
  }
}

/* ---------- Presentacion de cada partida ---------- */

function marcarPartida(partida) {
  if (!partida) return;
  const debe = valorNumerico(partida.querySelector('.debe'));
  const haber = valorNumerico(partida.querySelector('.haber'));
  partida.classList.toggle('con-debe', debe > 0 && haber === 0);
  partida.classList.toggle('con-haber', haber > 0 && debe === 0);
  partida.classList.toggle('partida-vacia', debe === 0 && haber === 0);
}

function actualizarResumenCuenta(partida) {
  const destino = partida.querySelector('.partida-resumen');
  const sel = partida.querySelector('.sel-cuenta');
  if (!destino || !sel) return;
  const op = sel.options[sel.selectedIndex];
  const texto = op && op.value ? op.textContent.replace(/^[\s\u00a0]+/, '') : '';
  destino.textContent = texto
    ? 'Cuenta elegida: ' + texto
    : 'Elija la cuenta de detalle donde se va a registrar el movimiento.';
  destino.classList.toggle('texto-ok', Boolean(texto));
}

function actualizarAyudaGrupo(partida) {
  const selGrupo = partida.querySelector('.sel-grupo');
  const pista = partida.querySelector('.partida-pista');
  if (!selGrupo || !pista) return;
  const op = selGrupo.options[selGrupo.selectedIndex];
  pista.textContent = op && op.value
    ? 'Dentro de: ' + op.textContent
    : 'Paso 1 de esta partida: elija el tipo de cuenta.';
}

/* ---------- Ciclo de vida de una partida ---------- */

function inicializarPartida(partida) {
  const selGrupo = partida.querySelector('.sel-grupo');
  const selCuenta = partida.querySelector('.sel-cuenta');
  const inpDebe = partida.querySelector('.debe');
  const inpHaber = partida.querySelector('.haber');

  selGrupo.addEventListener('change', () => {
    cargarCuentas(selGrupo, selCuenta, true);
    actualizarAyudaGrupo(partida);
    actualizarResumenCuenta(partida);
    const sel = selCuenta.options[selCuenta.selectedIndex];
    if (sel && sel.value) {
      inpDebe.focus();
      inpDebe.select();
    } else {
      selCuenta.focus();
    }
  });

  selCuenta.addEventListener('change', () => {
    actualizarResumenCuenta(partida);
    const op = selCuenta.options[selCuenta.selectedIndex];
    const hayMonto = valorNumerico(inpDebe) > 0 || valorNumerico(inpHaber) > 0;
    if (op && op.value && !hayMonto) {
      inpDebe.focus();
      inpDebe.select();
    }
  });

  campoNumerico(inpDebe, inpHaber);
  campoNumerico(inpHaber, inpDebe);

  partida.querySelector('.quitar-linea').addEventListener('click', () => {
    const todas = document.querySelectorAll('.partida');
    if (todas.length > 2) {
      partida.remove();
      renumerarLineas();
      recalcularTotales();
      const quedan = document.querySelectorAll('.partida');
      quedan[quedan.length - 1].scrollIntoView({ behavior: 'smooth', block: 'center' });
    } else {
      alert('Un asiento necesita al menos 2 partidas: una que suma y otra que resta.');
    }
  });

  // Al cargar no se elige cuenta automaticamente: solo se arma el filtro del grupo
  cargarCuentas(selGrupo, selCuenta, false);
  actualizarAyudaGrupo(partida);
  actualizarResumenCuenta(partida);
  marcarPartida(partida);
}

function cargarCuentas(selGrupo, selCuenta, seleccionarPrimera) {
  const codigo = selGrupo.value;
  const previo = selCuenta.value;
  let visibles = 0;
  Array.from(selCuenta.options).forEach(op => {
    if (!op.value) return;
    const coincide = codigo !== '' && op.dataset.grupo === codigo;
    op.hidden = !coincide;
    op.disabled = !coincide;
    if (coincide) visibles++;
  });
  selCuenta.value = '';
  const marcador = selCuenta.querySelector('option[value=""]');
  if (marcador) {
    marcador.textContent = codigo === ''
      ? 'Primero elija el tipo de cuenta'
      : (visibles ? 'Ahora elija la cuenta' : 'Este grupo no tiene cuentas de detalle');
  }

  const opPrevio = previo
    ? selCuenta.querySelector('option[value="' + previo + '"]')
    : null;

  if (opPrevio && !opPrevio.disabled) {
    selCuenta.value = previo;
  } else if (seleccionarPrimera !== false) {
    const primer = Array.from(selCuenta.options).find(op => op.value && !op.hidden);
    if (primer) selCuenta.value = primer.value;
  }
}

function renumerarLineas() {
  document.querySelectorAll('.partida').forEach((partida, i) => {
    const etiqueta = partida.querySelector('.num-linea');
    if (etiqueta) etiqueta.textContent = 'Partida ' + (i + 1);
  });
}

function agregarLinea() {
  const contenedor = document.getElementById('partidas');
  if (!contenedor) return;

  if (contenedor.querySelectorAll('.partida').length >= MAX_LINEAS) {
    alert('No se permiten mas de ' + MAX_LINEAS + ' partidas por asiento.');
    return;
  }

  // Clonar el <template> es la unica forma segura de crear markup de <tr>/<div>
  // sin que el navegador lo descarte al parsearlo dentro de otro contexto.
  const partida = document.getElementById('plantilla-partida')
    .content.firstElementChild.cloneNode(true);

  contenedor.appendChild(partida);
  inicializarPartida(partida);
  renumerarLineas();
  recalcularTotales();

  partida.scrollIntoView({ behavior: 'smooth', block: 'center' });
  partida.querySelector('.sel-grupo').focus();
}

function obtenerLineas() {
  return Array.from(document.querySelectorAll('.partida')).map((partida, i) => ({
    indice: i + 1,
    grupo: partida.querySelector('.sel-grupo').value,
    cuenta: partida.querySelector('.sel-cuenta').value,
    debe: valorNumerico(partida.querySelector('.debe')),
    haber: valorNumerico(partida.querySelector('.haber'))
  }));
}

function recalcularTotales() {
  const lineas = obtenerLineas();
  const totalDebe = lineas.reduce((s, l) => s + l.debe, 0);
  const totalHaber = lineas.reduce((s, l) => s + l.haber, 0);
  const dif = totalDebe - totalHaber;

  const td = document.getElementById('total-debe');
  const th = document.getElementById('total-haber');
  const d = document.getElementById('diferencia');
  const aviso = document.getElementById('aviso-partida');
  const mensaje = document.getElementById('mensaje-partida');
  const caja = document.getElementById('resumen-partida');

  if (td) td.textContent = formato(totalDebe);
  if (th) th.textContent = formato(totalHaber);

  if (d) {
    d.textContent = formato(dif);
    d.className = dif === 0 ? 'cuadra' : 'no-cuadra';
  }

  const cuadrando = dif === 0 && totalDebe > 0;
  if (aviso) {
    aviso.className = 'badge ' + (cuadrando ? 'bg-success' : 'bg-warning text-dark');
    aviso.textContent = cuadrando ? 'Listo para guardar' : 'Todavia no cuadra';
  }

  if (mensaje) {
    if (cuadrando) {
      mensaje.textContent = 'El Debe y el Haber son iguales (' + formato(totalDebe) + '). Ya puede guardar el asiento.';
    } else if (totalDebe === 0 && totalHaber === 0) {
      mensaje.textContent = 'Escriba el importe en el lado Debe o en el lado Haber de cada partida.';
    } else if (dif > 0) {
      mensaje.textContent = 'Sobran ' + formato(dif) + ' en el Debe. Agregue o aumente un Haber por ese valor.';
    } else {
      mensaje.textContent = 'Sobran ' + formato(Math.abs(dif)) + ' en el Haber. Agregue o aumente un Debe por ese valor.';
    }
  }

  if (caja) caja.classList.toggle('resumen-listo', cuadrando);

  return { totalDebe, totalHaber, dif };
}

function filtrarOpciones(texto) {
  const t = texto.trim().toLowerCase();
  document.querySelectorAll('.sel-grupo, .sel-cuenta').forEach(sel => {
    Array.from(sel.options).forEach(op => {
      op.hidden = t !== '' && !op.textContent.toLowerCase().includes(t);
    });
  });
}

function validarFormulario() {
  const errores = [];
  const fecha = document.getElementById('fecha').value;
  const concepto = document.getElementById('concepto').value.trim();

  if (!fecha) errores.push('La fecha es obligatoria.');
  if (concepto.length < 5) errores.push('El concepto es obligatorio y debe tener al menos 5 caracteres.');

  const lineas = obtenerLineas().filter(l => l.cuenta !== '' || l.debe > 0 || l.haber > 0);
  if (lineas.length < 2) errores.push('El asiento debe tener al menos dos partidas.');

  lineas.forEach(l => {
    if (!l.grupo) errores.push('Linea ' + l.indice + ': seleccione el tipo de cuenta.');
    if (!l.cuenta) errores.push('Linea ' + l.indice + ': seleccione la cuenta de detalle.');
    if (l.debe > 0 && l.haber > 0) errores.push('Linea ' + l.indice + ': no puede tener debito y credito a la vez.');
    if (l.debe === 0 && l.haber === 0) errores.push('Linea ' + l.indice + ': debe indicar el valor en debito o en credito.');
  });

  const totalDebe = lineas.reduce((s, l) => s + l.debe, 0);
  const totalHaber = lineas.reduce((s, l) => s + l.haber, 0);

  if (Math.abs(totalDebe - totalHaber) > 0.009) {
    errores.push('El asiento no cumple la partida doble: debe ' + formato(totalDebe) + ' y haber ' + formato(totalHaber) + '.');
  }
  if (totalDebe <= 0) errores.push('El total del asiento debe ser mayor que cero.');

  return errores;
}

document.addEventListener('DOMContentLoaded', () => {
  const contenedor = document.getElementById('partidas');
  if (!contenedor) return;

  contenedor.querySelectorAll('.partida').forEach(inicializarPartida);
  renumerarLineas();

  const botonesAgregar = document.querySelectorAll('#agregar-linea, #agregar-linea-2');
  botonesAgregar.forEach(btn => btn.addEventListener('click', agregarLinea));

  const buscador = document.getElementById('buscar-cuenta');
  if (buscador) {
    buscador.addEventListener('input', e => filtrarOpciones(e.target.value));
  }

  const formulario = document.getElementById('form-asiento');
  if (formulario) {
    formulario.addEventListener('submit', e => {
      const errores = validarFormulario();
      if (errores.length) {
        e.preventDefault();
        alert('No se puede guardar el asiento:\n\n- ' + errores.join('\n- '));
        recalcularTotales();
        return;
      }
      const btn = formulario.querySelector('button[type="submit"]');
      if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...'; }
    });
  }

  recalcularTotales();
});
