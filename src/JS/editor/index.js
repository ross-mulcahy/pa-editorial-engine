/**
 * PA Editorial Engine — Block editor extensions entry point.
 *
 * Registers sidebar panels, data stores, and editor modifications.
 */

// Feature: Nuclear Locking (Phase 2).
import '../features/locking/style.css';
import { initNuclearLocking } from '../features/locking';

// Feature: Metadata Engine (Phase 3).
import '../features/metadata/style.css';
import { initMetadataEngine } from '../features/metadata';

// Feature: Cloning Engine (Phase 4A).
import { initCloning } from '../features/cloning';

// Feature: Syndication & Correction Hooks (Phase 4B).
import { initSyndication } from '../features/syndication';

initNuclearLocking();
initMetadataEngine();
initCloning();
initSyndication();
