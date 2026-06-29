import CodeEditor from './CodeEditor';

export default function CodeViewer(props) {
    return <CodeEditor {...props} readOnly lint={false} />;
}
