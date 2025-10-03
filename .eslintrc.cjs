module.exports = {
  root: true,
  parser: '@typescript-eslint/parser',
  parserOptions: {
    project: ['./tsconfig.json', './tsconfig.eslint.json'],
    tsconfigRootDir: __dirname
  },
  plugins: ['@typescript-eslint', 'react', 'react-hooks'],
  extends: [
    'airbnb',
    'airbnb/hooks',
    'airbnb-typescript'
  ],
  rules: {
    'react/react-in-jsx-scope': 'off',
    'no-console': ['error', { allow: ['error', 'warn'] }]
  }
};
