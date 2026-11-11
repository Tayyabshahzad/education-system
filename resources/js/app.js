import './bootstrap';

import Alpine from 'alpinejs';
import proofUpload from './proof-compress';

window.Alpine = Alpine;

Alpine.data('proofUpload', proofUpload);

Alpine.start();
