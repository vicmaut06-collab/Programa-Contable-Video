const MAX_LINEAS = 50;

/* Catalogo completo, cargado una sola vez para que la cascada no espere al servidor */
let CATALOGO = { cargada: false, cuentas: [], porId: {}, hijos: {} };

function formato(valor) {
  const n = parseFloat(valor) || 0;
  return '$ ' + n.toLocaleString('es-SV', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function apiCuentas(params) {
  // El href del CSS trae un ?v= de version: se usa solo la ruta, sin la query.
  const link = document.querySelector('link[href*="assets/css/estilos.css"]');
  let prefijo = '';
  if (link) {
    const href = link.getAttribute('href') || '';
    prefijo = href.split('?')[0].split('assets/css/estilos.css')[0];
  }
  return fetch(prefijo + 'api/cuentas.php?' + new URLSearchParams(params).toString(), {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  }).then(r => r.json());
}

/* ---------- Catalogo en memoria ---------- */

function cargarCatalogo() {
  if (CATALOGO.cargada) return Promise.resolve(CATALOGO);
  return apiCuentas({}).then(r => {
    const cuentas = (r && r.cuentas) ? r.cuentas : [];
    const porId = {};
    const porCodigo = {};
    const hijos = {};
    cuentas.forEach(c => {
      porId[c.id] = c;
      porCodigo[c.codigo] = c;
      const p = c.padre_codigo || '';
      (hijos[p] = hijos[p] || []).push(c);
    });
    CATALOGO = { cargada: true, cuentas, porId, porCodigo, hijos };
    return CATALOGO;
  });
}

/** Todas las cuentas que cuelgan de un codigo, en cualquier profundidad. */
function descendientes(codigo) {
  const out = [];
  const pendientes = [codigo];
  while (pendientes.length) {
    const actual = pendientes.shift();
    (CATALOGO.hijos[actual] || []).forEach(c => {
      out.push(c);
      pendientes.push(c.codigo);
    });
  }
  return out.sort((a, b) => a.codigo.localeCompare(b.codigo));
}

/**
 * Las "cuentas de mayor" son las de nivel 4.
 * El nivel 2 (rubros de agrupacion) y el nivel 5 quedan solo como contexto,
 * y las hojas se eligen en el paso 3.
 */
function cuentasDeMayor(codigoGrupo) {
  return descendientes(codigoGrupo).filter(c => c.nivel === 4);
}

/** Sub cuentas: las hojas que cuelgan de la cuenta de mayor. */
function subCuentas(codigoMayor) {
  const hojas = descendientes(codigoMayor).filter(c => c.es_hoja === 1);
  if (hojas.length === 0) {
    // La propia cuenta de mayor ya es de detalle (caso 3102, 3103)
    const propia = buscarPorCodigo(codigoMayor);
    return propia ? [propia] : [];
  }
  return hojas;
}

function buscarPorCodigo(codigo) {
  return CATALOGO.porCodigo[codigo] || null;
}

/** El <select> guarda el id de la base; el arbol del catalogo se trabaja por codigo. */
function codigoDe(sel) {
  const op = sel.options[sel.selectedIndex];
  return op && op.value ? (op.dataset.codigo || '') : '';
}

/** El rubro de agrupacion (nivel 2, o el nivel 5 atipico) que encabezaria un grupo de opciones. */
function rubroDe(cuenta, codigoMayor) {
  const nivel = cuenta.nivel;
  if (nivel === 4) {
    // Buscar el ancestro de nivel 2
    let c = cuenta;
    while (c) {
      if (c.nivel === 2) return c;
      c = buscarPorCodigo(c.padre_codigo || '');
    }
    return null;
  }
  // Para las hojas: el padre directo suele ser el sub-rubro (nivel 6 o 5)
  const padre = buscarPorCodigo(cuenta.padre_codigo || '');
  if (padre && padre.codigo !== codigoMayor && padre.es_hoja === 0) return padre;
  return null;
}

function llenarOpciones(sel, opciones, marcador, etiquetaMayor) {
  sel.innerHTML = '';
  const vacio = document.createElement('option');
  vacio.value = '';
  vacio.textContent = marcador;
  sel.appendChild(vacio);

  // Agrupar por rubro de agrupacion: el rubro separa visualmente a las cuentas de mayor
  const grupos = new Map();
  opciones.forEach(op => {
    const rubro = rubroDe(op, etiquetaMayor);
    const clave = rubro ? rubro.codigo : '~';
    if (!grupos.has(clave)) grupos.set(clave, { rubro, items: [] });
    grupos.get(clave).items.push(op);
  });

  let cantidad = 0;
  grupos.forEach(({ rubro, items }) => {
    let padre = sel;
    if (rubro) {
      const og = document.createElement('optgroup');
      og.label = rubro.codigo + ' - ' + rubro.nombre;
      padre = og;
    }
    items.forEach(c => {
      const option = document.createElement('option');
      option.value = c.id;
      option.dataset.codigo = c.codigo;
      option.textContent = c.codigo + ' - ' + c.nombre;
      padre.appendChild(option);
      cantidad++;
    });
    if (rubro) sel.appendChild(padre);
  });

  return cantidad;
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
  const texto = op && op.value ? op.textContent.trim() : '';
  destino.textContent = texto
    ? 'Se registrara en: ' + texto
    : 'Elija la sub cuenta donde se va a registrar el movimiento.';
  destino.classList.toggle('texto-ok', Boolean(texto));
}

function actualizarAyudaGrupo(partida) {
  const selGrupo = partida.querySelector('.sel-grupo');
  const selMayor = partida.querySelector('.sel-mayor');
  const pista = partida.querySelector('.partida-pista');
  if (!selGrupo || !pista) return;

  const op = selGrupo.options[selGrupo.selectedIndex];
  if (!op || !op.value) {
    pista.textContent = 'Paso 1: elija el tipo de cuenta.';
    return;
  }

  const n = selMayor && selMayor.selectedIndex > -1
    ? (selMayor.options[selMayor.selectedIndex].textContent.match(/^\d+/) || [''])[0]
    : '';
  pista.textContent = n
    ? 'Dentro de ' + n + ' - ' + op.textContent.replace(/^\d+\s*·\s*/, '')
    : 'Paso 2: elija la cuenta de mayor de ' + op.textContent.replace(/^\d+\s*·\s*/, '') + '.';
}

/* ---------- Ciclo de vida de una partida ---------- */

function inicializarPartida(partida) {
  const selGrupo = partida.querySelector('.sel-grupo');
  const selMayor = partida.querySelector('.sel-mayor');
  const selCuenta = partida.querySelector('.sel-cuenta');
  const inpDebe = partida.querySelector('.debe');
  const inpHaber = partida.querySelector('.haber');

  selGrupo.addEventListener('change', () => {
    cargarMayores(selGrupo, selMayor, selCuenta, true);
    actualizarAyudaGrupo(partida);
    actualizarResumenCuenta(partida);
    selMayor.focus();
  });

  selMayor.addEventListener('change', () => {
    cargarSubCuentas(selMayor, selCuenta, true);
    actualizarAyudaGrupo(partida);
    actualizarResumenCuenta(partida);
    const op = selCuenta.options[selCuenta.selectedIndex];
    if (op && op.value) {
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

  restaurarPartida(partida);
  renumerarLineas();
  marcarPartida(partida);
}

/** Al editar un asiento se reconstruye la cascada a partir de la cuenta guardada. */
function restaurarPartida(partida) {
  const selGrupo = partida.querySelector('.sel-grupo');
  const selMayor = partida.querySelector('.sel-mayor');
  const selCuenta = partida.querySelector('.sel-cuenta');
  const cuentaId = partida.dataset.cuenta || '';

  if (!cuentaId || !CATALOGO.cargada) {
    selMayor.disabled = true;
    selCuenta.disabled = true;
    actualizarAyudaGrupo(partida);
    actualizarResumenCuenta(partida);
    return;
  }

  const cuenta = CATALOGO.porId[cuentaId];
  if (!cuenta) {
    selMayor.disabled = true;
    selCuenta.disabled = true;
    actualizarAyudaGrupo(partida);
    actualizarResumenCuenta(partida);
    return;
  }

  // 1) grupo
  let g = cuenta;
  while (g && g.nivel !== 1) g = buscarPorCodigo(g.padre_codigo || '');
  if (g) selGrupo.value = g.codigo;

  // 2) cuenta de mayor: se sube por la jerarquia hasta la cuenta de mayor (nivel 4)
  cargarMayores(selGrupo, selMayor, selCuenta, false);
  let m = buscarPorCodigo(cuenta.codigo);
  while (m && m.nivel !== 4) m = buscarPorCodigo(m.padre_codigo || '');
  if (m) selMayor.value = m.id;

  // 3) sub cuenta
  cargarSubCuentas(selMayor, selCuenta, false);
  selCuenta.value = cuenta.id;

  actualizarAyudaGrupo(partida);
  actualizarResumenCuenta(partida);
}

function cargarMayores(selGrupo, selMayor, selCuenta, seleccionarPrimera) {
  const codigo = selGrupo.value;
  selCuenta.innerHTML = '';
  selCuenta.disabled = true;
  selCuenta.value = '';

  if (codigo === '') {
    selMayor.innerHTML = '<option value="">Primero elija el tipo de cuenta</option>';
    selMayor.disabled = true;
    return 0;
  }

  const mayores = cuentasDeMayor(codigo);
  const n = llenarOpciones(
    selMayor,
    mayores,
    mayores.length ? 'Ahora elija la cuenta de mayor' : 'Este tipo de cuenta no tiene cuentas de mayor',
    ''
  );
  selMayor.disabled = false;
  if (seleccionarPrimera !== false && n > 0) {
    selMayor.value = selMayor.options[1].value;
    cargarSubCuentas(selMayor, selCuenta, true);
  }
  return n;
}

function cargarSubCuentas(selMayor, selCuenta, seleccionarPrimera) {
  const codigo = codigoDe(selMayor);

  if (codigo === '') {
    selCuenta.innerHTML = '<option value="">Primero elija la cuenta de mayor</option>';
    selCuenta.disabled = true;
    return 0;
  }

  const subs = subCuentas(codigo);
  const n = llenarOpciones(
    selCuenta,
    subs,
    subs.length ? 'Ahora elija la sub cuenta' : 'Esta cuenta de mayor no tiene sub cuentas',
    codigo
  );
  selCuenta.disabled = false;
  if (seleccionarPrimera !== false && n > 0) {
    selCuenta.value = selCuenta.options[1].value;
  }
  return n;
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
  cargarCatalogo().then(() => {
    inicializarPartida(partida);
    renumerarLineas();
    recalcularTotales();
    partida.scrollIntoView({ behavior: 'smooth', block: 'center' });
    partida.querySelector('.sel-grupo').focus();
  });
}

function obtenerLineas() {
  return Array.from(document.querySelectorAll('.partida')).map((partida, i) => ({
    indice: i + 1,
    grupo: partida.querySelector('.sel-grupo').value,
    mayor: partida.querySelector('.sel-mayor').value,
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
  document.querySelectorAll('.sel-grupo, .sel-mayor, .sel-cuenta').forEach(sel => {
    Array.from(sel.options).forEach(op => {
      if (op.hidden === undefined) return;
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
    if (!l.grupo) errores.push('Linea ' + l.indice + ': elija el tipo de cuenta.');
    if (!l.mayor) errores.push('Linea ' + l.indice + ': elija la cuenta de mayor.');
    if (!l.cuenta) errores.push('Linea ' + l.indice + ': elija la sub cuenta donde va el movimiento.');
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

  // El catalogo se carga una vez; sin el los desplegables 2 y 3 no se pueden llenar
  cargarCatalogo().then(() => {
    contenedor.querySelectorAll('.partida').forEach(inicializarPartida);
    renumerarLineas();
    recalcularTotales();
  }).catch(() => {
    contenedor.querySelectorAll('.partida .sel-mayor, .partida .sel-cuenta').forEach(sel => {
      sel.innerHTML = '<option value="">No se pudo cargar el catalogo de cuentas</option>';
    });
  });

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
