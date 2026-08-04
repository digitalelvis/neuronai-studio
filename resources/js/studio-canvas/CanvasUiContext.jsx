import { createContext, useContext } from 'react';

const CanvasUiContext = createContext({
    readOnly: false,
    agents: [],
    workflows: [],
    tools: [],
    mcpServers: [],
    knowledgeBases: [],
    ragSearchUrlTemplate: '',
    outputClasses: [],
    providers: {},
    providerModels: {},
    variables: [],
    defaultProvider: '',
    defaultModel: '',
    nodeTypesMeta: {},
});

export function CanvasUiProvider({
    readOnly = false,
    agents = [],
    workflows = [],
    tools = [],
    mcpServers = [],
    knowledgeBases = [],
    ragSearchUrlTemplate = '',
    outputClasses = [],
    providers = {},
    providerModels = {},
    variables = [],
    defaultProvider = '',
    defaultModel = '',
    nodeTypesMeta = {},
    children,
}) {
    return (
        <CanvasUiContext.Provider
            value={{
                readOnly,
                agents,
                workflows,
                tools,
                mcpServers,
                knowledgeBases,
                ragSearchUrlTemplate,
                outputClasses,
                providers,
                providerModels,
                variables,
                defaultProvider,
                defaultModel,
                nodeTypesMeta,
            }}
        >
            {children}
        </CanvasUiContext.Provider>
    );
}

export function useCanvasUi() {
    return useContext(CanvasUiContext);
}
