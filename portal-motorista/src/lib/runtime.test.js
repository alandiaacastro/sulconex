import { describe, expect, it, vi } from 'vitest';
import { buildQueryString, getErrorMessage, getPortalConfig, greeting, maskPhone, ROUTES } from './runtime.js';

describe('maskPhone', () => {
  it('formats a full mobile number', () => {
    expect(maskPhone('55999998888')).toBe('(55) 99999-8888');
  });

  it('ignores non-digits and limits to 11 digits', () => {
    expect(maskPhone('(55) 99999-8888777')).toBe('(55) 99999-8888');
  });
});

describe('greeting', () => {
  it('uses the provided first name', () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-03-29T10:00:00'));
    expect(greeting('Alan')).toBe('Bom dia, Alan');
    vi.useRealTimers();
  });
});

describe('getPortalConfig', () => {
  it('returns runtime config when available', () => {
    const previousWindow = global.window;
    global.window = {
      PORTAL_MOTORISTA_CONFIG: {
        apiBase: 'http://local/api',
        legacyLoginUrl: 'http://local/login',
        portalUrl: 'http://local/portal',
        cadastroRequestUrl: 'http://local/cadastro',
      },
    };

    expect(getPortalConfig()).toEqual({
      apiBase: 'http://local/api',
      legacyLoginUrl: 'http://local/login',
      portalUrl: 'http://local/portal',
      cadastroRequestUrl: 'http://local/cadastro',
    });

    global.window = previousWindow;
  });

  it('keeps the expected portal routes list', () => {
    expect(ROUTES).toEqual(['inicio', 'cargas', 'solicitacoes', 'andamento', 'documentos', 'contratos']);
  });
});

describe('buildQueryString', () => {
  it('skips empty values and keeps meaningful filters', () => {
    expect(buildQueryString({ origem: 'Curitiba', destino: '', page: 2, status: null })).toBe('origem=Curitiba&page=2');
  });
});

describe('getErrorMessage', () => {
  it('uses the error message when available', () => {
    expect(getErrorMessage(new Error('Falha ao carregar'))).toBe('Falha ao carregar');
  });

  it('falls back to a default message', () => {
    expect(getErrorMessage({})).toBe('Nao foi possivel concluir a operacao.');
  });
});
