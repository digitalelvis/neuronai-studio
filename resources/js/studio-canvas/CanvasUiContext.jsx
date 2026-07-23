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
            }}
        >
            {children}
        </CanvasUiContext.Provider>
    );
}

export function useCanvasUi() {
    return useContext(CanvasUiContext);
}
