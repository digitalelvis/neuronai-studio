import ForkBranchEditor from './editors/ForkBranchEditor';

export default function ForkNodeFields({
    data,
    readOnly = false,
    showControls = true,
    onUpdate,
}) {
    if (!showControls) {
        return null;
    }

    return <ForkBranchEditor data={data} readOnly={readOnly} onUpdate={onUpdate} />;
}
