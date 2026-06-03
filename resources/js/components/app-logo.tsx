import { useBrand } from '@/contexts/BrandContext';
import { getImagePath } from '@/utils/helpers';

export default function AppLogo({ position }: { position: 'left' | 'right' }) {
    const { logoLight, logoDark, titleText } = useBrand();
    
    const logoUrl = logoDark ? getImagePath(logoDark) : '/images/logos/logo-dark.png';
    const fallbackLogo = '/images/logos/logo-dark.png';

    return (
        <div className={`w-full flex items-center ${position === 'right' ? 'flex-row-reverse' : 'flex-row'}`}>
            <div className="flex aspect-square size-10 items-center justify-center rounded-md overflow-hidden mr-2">
                <img 
                    src={logoUrl.startsWith('http') ? logoUrl : (window.baseUrl + logoUrl.replace(/^\//, ''))} 
                    alt={titleText} 
                    className="max-h-full max-w-full object-contain"
                    onError={(e) => {
                        e.currentTarget.src = window.baseUrl + fallbackLogo.replace(/^\//, '');
                    }}
                />
            </div>
            <div className={`grid flex-1 truncate text-sm leading-none font-bold ${position === 'right' ? 'mr-1 text-right' : 'ml-1 text-left text-lg'}`}>
                {titleText}
            </div>
        </div>
    );
}
