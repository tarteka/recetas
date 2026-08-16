import type { ThemeOptions } from '@mui/material/styles';

const sans = 'Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
const serif = 'Georgia, "Times New Roman", serif';

export const temaRecetario: ThemeOptions = {
  palette: {
    mode: 'light',
    primary: {
      main: '#356044',
      dark: '#294c35',
      light: '#e7efe8',
      contrastText: '#ffffff',
    },
    secondary: {
      main: '#6c6b62',
      contrastText: '#ffffff',
    },
    background: {
      default: '#f7f5f0',
      paper: '#ffffff',
    },
    text: {
      primary: '#25251f',
      secondary: '#6c6b62',
    },
    divider: '#dedbd1',
    error: {
      main: '#a33b3b',
    },
  },
  typography: {
    fontFamily: sans,
    h1: { fontFamily: serif, fontWeight: 700, letterSpacing: '-0.035em' },
    h2: { fontFamily: serif, fontWeight: 700, letterSpacing: '-0.03em' },
    h3: { fontFamily: serif, fontWeight: 700, letterSpacing: '-0.025em' },
    h4: { fontFamily: serif, fontWeight: 700, letterSpacing: '-0.02em' },
    h5: { fontFamily: serif, fontWeight: 700 },
    h6: { fontFamily: serif, fontWeight: 700 },
    button: { fontWeight: 750, textTransform: 'none' },
  },
  shape: {
    borderRadius: 14,
  },
  shadows: [
    'none',
    '0 2px 8px rgb(38 45 35 / 4%)',
    '0 8px 24px rgb(38 45 35 / 8%)',
    '0 12px 32px rgb(38 45 35 / 10%)',
    '0 16px 40px rgb(38 45 35 / 11%)',
    '0 20px 48px rgb(38 45 35 / 12%)',
    '0 20px 55px rgb(38 45 35 / 13%)',
    ...Array(18).fill('0 20px 55px rgb(38 45 35 / 13%)'),
  ] as ThemeOptions['shadows'],
  components: {
    RaLayout: {
      styleOverrides: {
        root: {
          minWidth: 0,
        },
        contentWithSidebar: {
          minWidth: 0,
          maxWidth: '100%',
        },
        content: {
          minWidth: 0,
          maxWidth: '100%',
        },
      },
    },
    MuiCssBaseline: {
      styleOverrides: {
        body: {
          color: '#25251f',
          backgroundColor: '#f7f5f0',
        },
        '::selection': {
          color: '#25251f',
          backgroundColor: '#d8e6da',
        },
      },
    },
    MuiAppBar: {
      styleOverrides: {
        root: {
          color: '#25251f',
          backgroundColor: 'rgba(255, 255, 255, .94)',
          backgroundImage: 'none',
          borderBottom: '1px solid #dedbd1',
          boxShadow: '0 2px 8px rgb(38 45 35 / 4%)',
          backdropFilter: 'blur(12px)',
        },
      },
    },
    MuiDrawer: {
      styleOverrides: {
        paper: {
          backgroundColor: '#ffffff',
          backgroundImage: 'none',
          borderRight: '1px solid #dedbd1',
        },
      },
    },
    MuiPaper: {
      styleOverrides: {
        root: {
          backgroundImage: 'none',
          border: '1px solid #dedbd1',
        },
      },
    },
    MuiCard: {
      styleOverrides: {
        root: {
          borderRadius: 18,
          boxShadow: '0 8px 24px rgb(38 45 35 / 8%)',
        },
      },
    },
    MuiButton: {
      defaultProps: {
        disableElevation: true,
      },
      styleOverrides: {
        root: {
          minHeight: 42,
          borderRadius: 999,
          paddingInline: 18,
          '&.MuiButton-containedPrimary:hover': {
            backgroundColor: '#294c35',
          },
        },
      },
    },
    MuiIconButton: {
      styleOverrides: {
        root: {
          minWidth: 44,
          minHeight: 44,
          borderRadius: 12,
        },
      },
    },
    MuiListItemButton: {
      styleOverrides: {
        root: {
          margin: '4px 10px',
          borderRadius: 12,
          '&.Mui-selected': {
            color: '#356044',
            backgroundColor: '#e7efe8',
          },
          '&.Mui-selected:hover': {
            backgroundColor: '#d8e6da',
          },
        },
      },
    },
    MuiTableHead: {
      styleOverrides: {
        root: {
          backgroundColor: '#f2f0ea',
        },
      },
    },
    MuiTableCell: {
      styleOverrides: {
        head: {
          color: '#4f5049',
          fontSize: '.75rem',
          fontWeight: 800,
          letterSpacing: '.055em',
          textTransform: 'uppercase',
        },
        root: {
          borderBottomColor: '#e8e5dd',
        },
      },
    },
    MuiTableRow: {
      styleOverrides: {
        root: {
          '&:hover': {
            backgroundColor: '#f3f7f3',
          },
        },
      },
    },
    MuiOutlinedInput: {
      styleOverrides: {
        root: {
          backgroundColor: '#ffffff',
          borderRadius: 12,
          '&.Mui-focused .MuiOutlinedInput-notchedOutline': {
            borderColor: '#356044',
            borderWidth: 2,
          },
        },
        notchedOutline: {
          borderColor: '#cfcbbf',
        },
      },
    },
    MuiChip: {
      styleOverrides: {
        root: {
          color: '#356044',
          backgroundColor: '#e7efe8',
          borderRadius: 999,
          fontWeight: 700,
        },
      },
    },
    MuiPaginationItem: {
      styleOverrides: {
        root: {
          borderRadius: 999,
          '&.Mui-selected': {
            color: '#ffffff',
            backgroundColor: '#356044',
          },
        },
      },
    },
  },
};
