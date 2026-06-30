import { createContext, useContext } from 'react';

const CanvasUiContext = createContext({ readOnly: false, agents: [] });

export function CanvasUiProvider({ readOnly = false, agents = [], children }) {
    return <CanvasUiContext.Provider value={{ readOnly, agents }}>{children}</CanvasUiContext.Provider>;
}

export function useCanvasUi() {
    return useContext(CanvasUiContext);
}
