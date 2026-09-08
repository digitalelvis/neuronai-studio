import { Combobox } from '@/components/ui/combobox';
import { Label } from '@/components/ui/label';

export default function SkillNodeFields({ data, skills = [], readOnly = false, onUpdate }) {
    const updateField = (key, value) => {
        onUpdate?.({ ...data, [key]: value });
    };

    const skillOptions = skills.map((skill) => ({
        value: skill.ref,
        label: skill.label || skill.ref,
    }));

    return (
        <div className="space-y-2">
            <Label>Skill</Label>
            <Combobox
                options={skillOptions}
                value={data.skill_ref ?? ''}
                onValueChange={(value) => updateField('skill_ref', value)}
                placeholder="Select skill"
                searchPlaceholder="Search skills…"
                emptyText="No skills found."
                disabled={readOnly}
            />
            <p className="text-xs text-muted-foreground">
                Connect this node to an agent&apos;s violet skills handle. Skill nodes do not run as workflow steps.
            </p>
        </div>
    );
}
