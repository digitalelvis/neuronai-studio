import { createContext, useContext } from 'react';

const CanvasUiContext = createContext({ readOnly: false });

export function CanvasUiProvider({ readOnly = false, children }) {
    return <CanvasUiContext.Provider value={{ readOnly }}>{children}</CanvasUiContext.Provider>;
}

export function useCanvasUi() {
    return useContext(CanvasUiContext);
}
