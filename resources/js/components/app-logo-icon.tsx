import { NotebookPen } from 'lucide-react';
import type { ComponentProps } from 'react';

export default function AppLogoIcon(props: ComponentProps<typeof NotebookPen>) {
    return <NotebookPen {...props} />;
}
