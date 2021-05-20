import Airwire from '.';
import './airwired'

declare module 'airwire' {
    export interface TypeMap {
        String: any;
    }
}

declare global {
    interface Window {
        Airwire: Airwire
    }
}
