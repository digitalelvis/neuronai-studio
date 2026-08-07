import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

function normalizeBranches(branches) {
    if (!Array.isArray(branches)) {
        return [];
    }

    return branches
        .map((branch) => (typeof branch === 'string' ? branch : branch?.id))
        .filter((id) => typeof id === 'string' && id !== '');
}

export default function ForkBranchEditor({ data, readOnly, onUpdate }) {
    const branches = normalizeBranches(data.branches);

    const commit = (next) => {
        onUpdate?.({ ...data, branches: next });
    };

    const addBranch = () => {
        const next = [...branches, `branch_${branches.length + 1}`];
        commit(next);
    };

    const renameBranch = (index, value) => {
        const next = branches.map((id, i) => (i === index ? value : id));
        commit(next);
    };

    const removeBranch = (index) => {
        commit(branches.filter((_, i) => i !== index));
    };

    return (
        <div className="space-y-3">
            <div>
                <Label>Branches</Label>
                <p className="text-xs text-muted-foreground">
                    Each branch adds a named output handle. Draw an edge from the handle to the
                    branch subgraph, then converge every branch back into a join node.
                </p>
            </div>

            {branches.length === 0 && (
                <p className="text-xs text-muted-foreground">No branches yet.</p>
            )}

            {branches.map((branchId, index) => (
                <div key={index} className="flex items-center gap-2">
                    <Input
                        value={branchId}
                        onChange={(e) => renameBranch(index, e.target.value)}
                        disabled={readOnly}
                        className="font-mono text-xs"
                    />
                    {!readOnly && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => removeBranch(index)}
                            title="Remove branch"
                        >
                            ✕
                        </Button>
                    )}
                </div>
            ))}

            {!readOnly && (
                <Button variant="outline" size="sm" onClick={addBranch}>
                    Add Branch
                </Button>
            )}
        </div>
    );
}
