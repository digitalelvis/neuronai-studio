import { createContext, useContext } from 'react';

const CanvasUiContext = createContext({
    readOnly: false,
    agents: [],
    tools: [],
    mcpServers: [],
    knowledgeBases: [],
    ragSearchUrlTemplate: '',
    outputClasses: [],
    providers: {},
    providerModels: {},
    defaultProvider: '',
    defaultModel: '',
    nodeTypesMeta: {},
});

export function CanvasUiProvider({
    readOnly = false,
    agents = [],
    tools = [],
    mcpServers = [],
    knowledgeBases = [],
    ragSearchUrlTemplate = '',
    outputClasses = [],
    providers = {},
    providerModels = {},
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
                tools,
                mcpServers,
                knowledgeBases,
                ragSearchUrlTemplate,
                outputClasses,
                providers,
                providerModels,
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
