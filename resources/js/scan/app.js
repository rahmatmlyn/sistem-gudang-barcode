import Alpine from 'alpinejs';
import { cameraScanner } from './camera.js';
import { scanInput } from './scanner.js';
import { opname } from './modes/opname.js';
import { penerimaan } from './modes/penerimaan.js';
import { packing, picking } from './modes/picking.js';
import { putaway, transfer } from './modes/pindah.js';

Alpine.store('scan', { paused: false, online: true });
Alpine.data('scanInput', scanInput);
Alpine.data('cameraScanner', cameraScanner);
Alpine.data('penerimaan', penerimaan);
Alpine.data('putaway', putaway);
Alpine.data('transfer', transfer);
Alpine.data('picking', picking);
Alpine.data('packing', packing);
Alpine.data('opname', opname);

window.Alpine = Alpine;
Alpine.start();
