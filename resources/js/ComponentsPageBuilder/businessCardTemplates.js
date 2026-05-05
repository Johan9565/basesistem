import bcMinimal from '@/ComponentsPageBuilder/templates/bc-minimal.html?raw';
import bcDark from '@/ComponentsPageBuilder/templates/bc-dark.html?raw';
import bcCreative from '@/ComponentsPageBuilder/templates/bc-creative.html?raw';
import bcElegant from '@/ComponentsPageBuilder/templates/bc-elegant.html?raw';
import bcTech from '@/ComponentsPageBuilder/templates/bc-tech.html?raw';
import bcCorporate from '@/ComponentsPageBuilder/templates/bc-corporate.html?raw';
import bcPhotography from '@/ComponentsPageBuilder/templates/bc-photography.html?raw';
import bcRetro from '@/ComponentsPageBuilder/templates/bc-retro.html?raw';
import bcNature from '@/ComponentsPageBuilder/templates/bc-nature.html?raw';
import bcGeometric from '@/ComponentsPageBuilder/templates/bc-geometric.html?raw';

export const businessCardTemplates = [
    { title: 'Minimalista', html_code: String(bcMinimal ?? '').trim() },
    { title: 'Oscura', html_code: String(bcDark ?? '').trim() },
    { title: 'Creativa', html_code: String(bcCreative ?? '').trim() },
    { title: 'Elegante', html_code: String(bcElegant ?? '').trim() },
    { title: 'Tech', html_code: String(bcTech ?? '').trim() },
    { title: 'Corporativa', html_code: String(bcCorporate ?? '').trim() },
    { title: 'Fotografía', html_code: String(bcPhotography ?? '').trim() },
    { title: 'Retro', html_code: String(bcRetro ?? '').trim() },
    { title: 'Naturaleza', html_code: String(bcNature ?? '').trim() },
    { title: 'Geométrica', html_code: String(bcGeometric ?? '').trim() },
];
