// Lettura dei file di import nel browser. Il risultato passa poi da validateDevice()
// come se fosse stato digitato nel form, quindi le regole sono le stesse.

/**
 * File XML della versione originale (vedi data.xml):
 * <data><identifier/><matrix><row><col1/><col2/></row>...</matrix><vector><value/>...</vector></data>
 */
export function parseDeviceXml(text) {
  const doc = new DOMParser().parseFromString(text, 'application/xml')
  if (doc.getElementsByTagName('parsererror').length) {
    throw new Error('The file is not valid XML')
  }
  const root = doc.documentElement
  const textOf = (el, tag) => el.getElementsByTagName(tag)[0]?.textContent.trim() ?? ''
  const matrixEl = root.getElementsByTagName('matrix')[0]
  const vectorEl = root.getElementsByTagName('vector')[0]
  if (!matrixEl) throw new Error('Missing <matrix> element')
  return {
    identifier: textOf(root, 'identifier'),
    matrix: [...matrixEl.getElementsByTagName('row')].map((row) => [textOf(row, 'col1'), textOf(row, 'col2')]),
    vector: vectorEl ? [...vectorEl.getElementsByTagName('value')].map((v) => v.textContent.trim()) : [],
  }
}

/**
 * CSV come in import.php originale: una riga per punto
 *   identifier,col1,col2,v1,v2,...
 * Le righe con lo stesso identifier formano un dispositivo; il vettore è preso dalla prima.
 * Separatore ',' oppure ';'. Righe vuote e una eventuale intestazione ("identifier,...") vengono ignorate.
 */
export function parseDevicesCsv(text) {
  const devices = new Map()
  const lines = text.split(/\r?\n/).filter((l) => l.trim())
  // Separatore ';' (Excel italiano, con virgola decimale) oppure ','
  const sep = lines[0]?.includes(';') ? ';' : ','
  lines.forEach((line, n) => {
    const cells = line.split(sep).map((c) => c.trim().replace(/^"(.*)"$/, '$1'))
    if (n === 0 && cells[0].toLowerCase() === 'identifier') return
    const [identifier, col1 = '', col2 = '', ...vector] = cells
    if (!devices.has(identifier)) devices.set(identifier, { identifier, matrix: [], vector })
    devices.get(identifier).matrix.push([col1, col2])
  })
  return [...devices.values()]
}
